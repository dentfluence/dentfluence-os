<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ConsentLog;
use App\Models\DataBreach;
use App\Models\DataRequest;
use App\Models\Patient;
use App\Models\RetentionPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RetentionService (DPDP 5.4)
 * ---------------------------
 * Read-only by default. Given a retention policy, it can tell you HOW MANY
 * records are past their retention window (the "dry run"). It deliberately
 * does NOT delete anything — purging is a separate, explicit step that must be
 * enabled with sign-off, because it is destructive.
 */
class RetentionService
{
    /** Build the query of records that are OLDER than the policy's window. */
    public function candidateQuery(RetentionPolicy $policy): ?Builder
    {
        $cutoff = Carbon::now()->subDays($policy->retain_days);

        return match ($policy->data_type) {
            'audit_logs'        => AuditLog::where('created_at', '<', $cutoff),
            'consent_logs'      => ConsentLog::where('created_at', '<', $cutoff),
            'data_requests'     => DataRequest::whereIn('status', ['completed', 'rejected'])->where('resolved_at', '<', $cutoff),
            'breaches'          => DataBreach::where('status', 'closed')->where('updated_at', '<', $cutoff),
            'inactive_patients' => Patient::where('updated_at', '<', $cutoff),
            default             => null,
        };
    }

    /** How many records would be affected right now (dry run). */
    public function candidateCount(RetentionPolicy $policy): ?int
    {
        $q = $this->candidateQuery($policy);
        return $q?->count();
    }

    /**
     * Dry-run summary across all active policies.
     * @return Collection<int, array{policy: RetentionPolicy, count: ?int, cutoff: string}>
     */
    public function report(): Collection
    {
        return RetentionPolicy::active()->orderBy('data_type')->get()->map(fn (RetentionPolicy $p) => [
            'policy' => $p,
            'count'  => $this->candidateCount($p),
            'cutoff' => Carbon::now()->subDays($p->retain_days)->toDateString(),
        ]);
    }
}
