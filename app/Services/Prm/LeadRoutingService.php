<?php

namespace App\Services\Prm;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * LeadRoutingService — PRM Phase 2a (auto-assign).
 * ----------------------------------------------------------------------------
 * Picks the right staff member for a new lead so nothing sits unassigned.
 *
 *   • Pool   = active users whose role is "assignable" (config), optionally
 *              narrowed by a per-treatment role override and/or branch.
 *   • Choice = least-loaded (fewest open leads) by default — self-balancing,
 *              no cursor state to maintain. 'random' is also supported.
 *
 * Writes BOTH assigned_to_id (real user FK) and assigned_to (name string) so
 * every existing view keeps showing the assignee with no changes.
 */
class LeadRoutingService
{
    /**
     * Assign a lead to a staff user. Returns the chosen User, or null if it
     * was skipped (already assigned) or no eligible staff exist.
     */
    public function assign(Lead $lead, bool $persist = true): ?User
    {
        if ($this->shouldSkip($lead)) {
            return null;
        }

        $user = $this->pick($lead);
        if (! $user) {
            return null; // no eligible staff — leave unassigned, don't error.
        }

        if ($persist) {
            $lead->forceFill([
                'assigned_to_id' => $user->id,
                'assigned_to'    => $user->name,
            ])->saveQuietly(); // quiet → don't re-fire the observer.

            $lead->activities()->create([
                'type'          => 'note',
                'label'         => 'Auto-assigned',
                'note'          => "Lead auto-assigned to {$user->name}.",
                'activity_date' => today(),
                'activity_time' => now()->format('h:i A'),
                'by'            => 'System (Auto-assign)',
            ]);
        }

        return $user;
    }

    /**
     * Skip if routing should not override what's already there.
     */
    protected function shouldSkip(Lead $lead): bool
    {
        if (! config('prm.routing.respect_manual')) {
            return false;
        }
        return ! empty($lead->assigned_to_id) || ! empty($lead->assigned_to);
    }

    /**
     * Choose a user from the eligible pool by the configured strategy.
     */
    protected function pick(Lead $lead): ?User
    {
        $candidates = $this->candidatePool($lead);
        if ($candidates->isEmpty()) {
            return null;
        }

        return match (config('prm.routing.strategy', 'least_loaded')) {
            'random' => $candidates->random(),
            default  => $this->leastLoaded($candidates),
        };
    }

    /**
     * Active users eligible to receive this lead.
     */
    protected function candidatePool(Lead $lead): Collection
    {
        $slugs = $this->rolesForLead($lead);

        $roleIds = Role::whereIn('slug', $slugs)->pluck('id');

        $query = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($roleIds, $slugs) {
                // New role system (role_id) OR legacy role string.
                $q->whereIn('role_id', $roleIds)
                  ->orWhereIn('role', $slugs);
            });

        if (config('prm.routing.restrict_to_branch') && ! empty($lead->branch_id)) {
            $query->where('branch_id', $lead->branch_id);
        }

        return $query->get();
    }

    /**
     * Which role slugs are eligible for this lead — the general pool, unless a
     * per-treatment override applies AND staff with that role actually exist.
     */
    protected function rolesForLead(Lead $lead): array
    {
        $default = config('prm.routing.assignable_roles', ['front_desk', 'manager']);

        $map       = config('prm.routing.treatment_roles', []);
        $treatment = strtolower((string) ($lead->ai_treatment_label ?: $lead->treatment));

        if ($treatment !== '' && isset($map[$treatment])) {
            $override = (array) $map[$treatment];
            // Only honour the override if such staff exist; else fall back.
            $exists = User::where('is_active', true)
                ->whereHas('roleModel', fn ($q) => $q->whereIn('slug', $override))
                ->exists();
            if ($exists) {
                return $override;
            }
        }

        return $default;
    }

    /**
     * Pick the candidate with the fewest open (not converted/lost) leads.
     */
    protected function leastLoaded(Collection $candidates): User
    {
        $counts = Lead::whereIn('assigned_to_id', $candidates->pluck('id'))
            ->whereNotIn('stage', ['converted', 'lost'])
            ->selectRaw('assigned_to_id, COUNT(*) as c')
            ->groupBy('assigned_to_id')
            ->pluck('c', 'assigned_to_id');

        // Sort by open-lead count, then user id for a stable tiebreak.
        return $candidates
            ->sortBy(fn (User $u) => [(int) ($counts[$u->id] ?? 0), $u->id])
            ->first();
    }
}
