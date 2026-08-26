<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * AppointmentPolicy — 2026-08-26
 *
 * The app had NO policies before this one; `module:appointments` middleware
 * was the only gate, and it is a yes/no on the whole module. Two consequences
 * this policy closes:
 *
 *   1. BRANCH — defence in depth only. `BranchScope` (the global scope on
 *      every BelongsToBranch model) already 404s a cross-branch record for
 *      non-admins at route-model-binding time. This check is the same rule
 *      restated for any query that runs `withoutGlobalScope(BranchScope)`.
 *      It mirrors BranchScope EXACTLY, admin exemption included — two rules
 *      about branches that disagree is worse than one that is slightly loose.
 *
 *   2. DOCTOR SCOPE. Enforces the own-appointments boundary for roles the
 *      clinic has locked down (User::APPT_SCOPE_OWN_ONLY). Doctors on the
 *      default scope (own_default) keep full branch access here — for them
 *      the scoping is a calendar view default, not a permission.
 *
 * Module-level view/edit/delete rights stay where they already live: the
 * `module:appointments` middleware on the route. This policy answers only
 * "which records", never "which actions".
 */
class AppointmentPolicy
{
    /** Read one appointment (show / quick view / edit form). */
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->sameBranch($user, $appointment)
            && $this->withinScope($user, $appointment);
    }

    /** Any write to one appointment (update, status, cancel, revert, hide, …). */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    /**
     * Branch isolation, mirroring App\Models\Scopes\BranchScope.
     *
     * The admin exemption is BranchScope's own deliberate rule ("admins /
     * clinic owners legitimately see every branch"), not an oversight — a
     * multi-branch owner must be able to open branch 2's day sheet. If that
     * decision is ever revisited, it must be revisited in BranchScope and
     * here together.
     */
    private function sameBranch(User $user, Appointment $appointment): bool
    {
        if ($user->isAdminRole()) {
            return true;
        }

        return (int) $appointment->branch_id === (int) $user->branch_id;
    }

    /**
     * Record-level scope. Only a HARD own_only boundary restricts anything;
     * `all` and `own_default` both allow the whole branch.
     */
    private function withinScope(User $user, Appointment $appointment): bool
    {
        if (! $user->lockedToOwnAppointments()) {
            return true;
        }

        return (int) $appointment->doctor_id === (int) $user->id;
    }
}
