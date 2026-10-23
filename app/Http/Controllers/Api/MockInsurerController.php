<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockInsurerPolicy;
use App\Services\MockInsurerService;
use App\Services\PolicyIssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockInsurerController extends Controller
{
    public function __construct(
        protected PolicyIssuanceService $issuanceService,
        protected MockInsurerService $mockInsurer
    ) {}

    /**
     * POST /api/mock-insurer/callback
     * Receive a simulated provider event webhook.
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string'],
            'provider_reference' => ['required', 'string'],
            'event_type' => ['nullable', 'string'],
            'policy_number' => ['nullable', 'string'],
        ]);

        $result = $this->issuanceService->processCallback($request->all());

        return response()->json($result, 200);
    }

    /**
     * GET /api/mock-insurer/policies/{provider_reference}
     * External status lookup endpoint.
     */
    public function lookup(string $providerReference): JsonResponse
    {
        $record = $this->mockInsurer->lookupPolicyByReference($providerReference);

        if (! $record) {
            return response()->json([
                'status' => 'not_found',
                'message' => "No mock insurer record found for reference [{$providerReference}].",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record,
        ]);
    }

    /**
     * Helper endpoint for UI sandbox & testing:
     * Dispatches simulated webhook callback for a provider reference.
     */
    public function triggerCallback(Request $request, string $providerReference): JsonResponse
    {
        $record = MockInsurerPolicy::where('provider_reference', $providerReference)->first();

        if (! $record) {
            return response()->json([
                'status' => 'not_found',
                'message' => "Provider reference [{$providerReference}] not found in mock ledger.",
            ], 404);
        }

        // Custom event_id or generate one
        $eventId = $request->input('event_id', 'EVT-' . strtoupper(Str::random(10)));

        $payload = [
            'event_id' => $eventId,
            'provider_reference' => $record->provider_reference,
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => $record->policy_number,
            'timestamp' => now()->toIso8601String(),
        ];

        $result = $this->issuanceService->processCallback($payload);

        return response()->json([
            'dispatched_payload' => $payload,
            'callback_result' => $result,
        ]);
    }
}
