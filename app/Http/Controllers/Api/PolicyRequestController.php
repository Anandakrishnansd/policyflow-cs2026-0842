<?php

namespace App\Http\Controllers\Api;

use App\Enums\PolicyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequest;
use App\Models\PolicyRequest;
use App\Services\PolicyIssuanceService;
use App\Services\PricingEngine;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PricingEngine $pricingEngine,
        protected PolicyIssuanceService $issuanceService
    ) {}

    /**
     * POST /api/policy-requests
     * Create a new policy request or safely return existing request on repeated submission.
     * HTTP 201: newly created
     * HTTP 200: safely repeated (idempotent duplicate)
     */
    public function store(StorePolicyRequest $request): JsonResponse
    {
        $agentId = $request->input('agent_id');
        $clientRequestId = $request->input('client_request_id');

        // Check if logical request already exists (Duplicate Intake Protection)
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->with(['stateHistories'])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'duplicate_request_returned',
                'message' => 'Request with matching agent_id and client_request_id already exists. Returning persisted record.',
                'is_duplicate' => true,
                'data' => $existing,
            ], 200);
        }

        // Calculate deterministic premium snapshot
        $pricing = $this->pricingEngine->calculate(
            planCode: $request->input('plan_code'),
            destination: $request->input('destination'),
            sumInsured: (int) $request->input('sum_insured'),
            dateOfBirth: $request->input('date_of_birth'),
            tripStart: $request->input('trip_start'),
            tripEnd: $request->input('trip_end')
        );

        try {
            $policy = DB::transaction(function () use ($request, $pricing, $agentId, $clientRequestId) {
                $created = PolicyRequest::create([
                    'client_request_id' => $clientRequestId,
                    'agent_id' => $agentId,
                    'customer_name' => $request->input('customer_name'),
                    'date_of_birth' => $request->input('date_of_birth'),
                    'destination' => strtoupper($request->input('destination')),
                    'trip_start' => $request->input('trip_start'),
                    'trip_end' => $request->input('trip_end'),
                    'travel_days' => $pricing['travel_days'],
                    'plan_code' => strtoupper($request->input('plan_code')),
                    'sum_insured' => (int) $request->input('sum_insured'),
                    'premium_amount' => $pricing['premium_amount'],
                    'premium_breakdown' => $pricing['breakdown'],
                    'status' => PolicyStatus::VALIDATED, // Validated and ready for submission
                ]);

                // Initial audit trail
                $created->stateHistories()->create([
                    'from_status' => null,
                    'to_status' => PolicyStatus::VALIDATED->value,
                    'reason' => 'Intake validated successfully and premium calculated.',
                    'metadata' => [
                        'premium_amount' => $pricing['premium_amount'],
                        'travel_days' => $pricing['travel_days'],
                    ],
                    'created_at' => now(),
                ]);

                return $created;
            });

            return response()->json([
                'status' => 'created',
                'message' => 'Policy request created and validated successfully.',
                'is_duplicate' => false,
                'data' => $policy->fresh(['stateHistories']),
            ], 201);

        } catch (QueryException $e) {
            // Concurrency catch: In high-concurrency race conditions where two simultaneous
            // requests pass the select check together, DB UNIQUE(agent_id, client_request_id)
            // will catch the race and prevent duplicate rows.
            $duplicate = PolicyRequest::where('agent_id', $agentId)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'status' => 'duplicate_request_returned',
                    'message' => 'Concurrent duplicate intake detected and safely resolved via DB unique constraint.',
                    'is_duplicate' => true,
                    'data' => $duplicate,
                ], 200);
            }

            throw $e;
        }
    }

    /**
     * GET /api/policy-requests/{id}
     * Retrieve status, premium, references and full audit trail.
     */
    public function show(string $id): JsonResponse
    {
        $policy = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])
            ->find($id);

        if (! $policy) {
            return response()->json([
                'status' => 'not_found',
                'message' => "Policy request [{$id}] does not exist.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $policy,
        ]);
    }

    /**
     * GET /api/policy-requests
     * List requests with status filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('client_request_id', 'like', "%{$search}%")
                  ->orWhere('policy_number', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $policies = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $policies->items(),
            'meta' => [
                'current_page' => $policies->currentPage(),
                'last_page' => $policies->lastPage(),
                'per_page' => $policies->perPage(),
                'total' => $policies->total(),
            ],
        ]);
    }

    /**
     * POST /api/policy-requests/{id}/submit
     * Start or safely resume issuance with optional scenario simulation.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $policy = PolicyRequest::find($id);
        if (! $policy) {
            return response()->json([
                'status' => 'not_found',
                'message' => "Policy request [{$id}] not found.",
            ], 404);
        }

        $scenario = $request->input('scenario', 'SUCCESS');
        $result = $this->issuanceService->submit($policy, $scenario);

        $httpCode = match ($result['status']) {
            'issued', 'already_issued', 'reconciled_to_issued' => 200,
            'pending_confirmation' => 202,
            'timed_out' => 504,
            'failed' => 502,
            default => 200,
        };

        return response()->json($result, $httpCode);
    }

    /**
     * POST /api/policy-requests/{id}/reconcile
     * Explicitly reconcile policy status against mock provider ledger.
     */
    public function reconcile(string $id): JsonResponse
    {
        $policy = PolicyRequest::find($id);
        if (! $policy) {
            return response()->json([
                'status' => 'not_found',
                'message' => "Policy request [{$id}] not found.",
            ], 404);
        }

        $result = $this->issuanceService->reconcile($policy);

        return response()->json($result);
    }
}
