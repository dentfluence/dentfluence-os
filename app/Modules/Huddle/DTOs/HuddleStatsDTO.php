<?php

declare(strict_types=1);

namespace App\Modules\Huddle\DTOs;

/**
 * Aggregated stats for the top strip of the huddle board.
 */
final class HuddleStatsDTO
{
    public function __construct(
        public readonly int $totalAppointments,
        public readonly int $confirmed,
        public readonly int $checkedIn,
        public readonly int $inChair,
        public readonly int $done,
        public readonly int $cancelled,
        public readonly int $noShow,
        public readonly int $pendingTasks,
        public readonly int $overdueTasks,
        public readonly int $escalatedTasks,
    ) {}

    public function toArray(): array
    {
        return [
            'total_appointments' => $this->totalAppointments,
            'confirmed'          => $this->confirmed,
            'checked_in'         => $this->checkedIn,
            'in_chair'           => $this->inChair,
            'done'               => $this->done,
            'cancelled'          => $this->cancelled,
            'no_show'            => $this->noShow,
            'pending_tasks'      => $this->pendingTasks,
            'overdue_tasks'      => $this->overdueTasks,
            'escalated_tasks'    => $this->escalatedTasks,
        ];
    }
}
