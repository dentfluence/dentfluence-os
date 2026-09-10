<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\Lead;
use App\Models\TodayActionDismissal;
use App\Models\TreatmentOpportunity;
use Illuminate\Database\Eloquent\Model;

/**
 * W-10 (2026-09-10). A permanent close on a Today's Actions row ("Stop
 * chasing", "Not needed", a closes_task outcome) is a statement about ONE
 * occurrence: this appointment on this date, this opportunity due on this
 * date. When staff move that date, the next occurrence is new work and must
 * come back to the board — otherwise a rescheduled appointment would never
 * get its confirmation call again.
 *
 * One observer, three models, one column each. Registered in
 * AppServiceProvider next to the other model observers.
 */
class TodayActionDismissalLiftObserver
{
    /** model class => the column whose change makes the row new again */
    private const DRIVING_DATE = [
        Appointment::class          => 'appointment_date',
        Lead::class                 => 'followup_date',
        TreatmentOpportunity::class => 'follow_up_date',
    ];

    public function updated(Model $model): void
    {
        $column = self::DRIVING_DATE[get_class($model)] ?? null;

        if ($column && $model->wasChanged($column)) {
            TodayActionDismissal::liftFor($model);
        }
    }
}
