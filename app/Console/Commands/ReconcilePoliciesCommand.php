<?php

namespace App\Console\Commands;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Services\PolicyIssuanceService;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:reconcile 
                            {--id= : Target a specific Policy Request UUID} 
                            {--limit=50 : Maximum number of policies to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and resolve unconfirmed, timed-out, or stalled policies against external provider';

    /**
     * Execute the console command.
     */
    public function handle(PolicyIssuanceService $service): int
    {
        $this->info('Starting PolicyFlow reconciliation scan...');

        $query = PolicyRequest::query();

        if ($targetId = $this->option('id')) {
            $query->where('id', $targetId);
        } else {
            $query->whereIn('status', [
                PolicyStatus::NEEDS_RECONCILIATION,
                PolicyStatus::PENDING_CONFIRMATION,
            ]);
        }

        $policies = $query->limit((int) $this->option('limit'))->get();

        if ($policies->isEmpty()) {
            $this->info('No policies currently requiring reconciliation.');
            return Command::SUCCESS;
        }

        $this->info("Found {$policies->count()} policy/policies to reconcile.");

        $rows = [];
        foreach ($policies as $policy) {
            $prevStatus = $policy->status->value;
            $res = $service->reconcile($policy);
            $policy->refresh();

            $rows[] = [
                $policy->id,
                $policy->client_request_id,
                $policy->provider_reference ?? 'N/A',
                $prevStatus,
                $policy->status->value,
                $policy->policy_number ?? 'Pending',
                $res['message'] ?? 'OK',
            ];
        }

        $this->table(
            ['Policy ID', 'Client Req ID', 'Provider Ref', 'From Status', 'To Status', 'Policy #', 'Result'],
            $rows
        );

        $this->info('Reconciliation run completed successfully.');
        return Command::SUCCESS;
    }
}
