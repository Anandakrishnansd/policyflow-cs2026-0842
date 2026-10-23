<?php

namespace App\Http\Controllers;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Services\PricingEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(PricingEngine $pricing): View
    {
        $policies = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => PolicyRequest::count(),
            'issued' => PolicyRequest::where('status', PolicyStatus::ISSUED)->count(),
            'needs_reconciliation' => PolicyRequest::where('status', PolicyStatus::NEEDS_RECONCILIATION)->count(),
            'pending_confirmation' => PolicyRequest::where('status', PolicyStatus::PENDING_CONFIRMATION)->count(),
            'failed' => PolicyRequest::where('status', PolicyStatus::FAILED)->count(),
            'validated' => PolicyRequest::where('status', PolicyStatus::VALIDATED)->count(),
            'total_premium' => PolicyRequest::where('status', PolicyStatus::ISSUED)->sum('premium_amount'),
        ];

        return view('dashboard', [
            'policies' => $policies,
            'stats' => $stats,
            'baseRates' => PricingEngine::BASE_RATES,
            'destMultipliers' => PricingEngine::DESTINATION_MULTIPLIERS,
            'covMultipliers' => PricingEngine::COVERAGE_MULTIPLIERS,
        ]);
    }
}
