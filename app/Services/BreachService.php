<?php

namespace App\Services;

use App\Models\DataBreach;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * BreachService (DPDP 5.3)
 * ------------------------
 * Logging a breach and recording the two DPDP milestones:
 * reporting it to the Data Protection Board and notifying affected patients.
 */
class BreachService
{
    public function log(array $data): DataBreach
    {
        return DataBreach::create(array_merge($data, [
            'reference'  => $this->nextReference(),
            'created_by' => Auth::id(),
            'status'     => $data['status'] ?? 'open',
        ]));
    }

    public function markReportedToBoard(DataBreach $breach, ?string $boardReference = null): DataBreach
    {
        $breach->update([
            'reported_to_board_at' => Carbon::now(),
            'board_reference'      => $boardReference,
            'status'               => in_array($breach->status, ['open', 'contained'], true) ? 'reported' : $breach->status,
        ]);
        return $breach;
    }

    public function markPatientsNotified(DataBreach $breach): DataBreach
    {
        $breach->update(['patients_notified_at' => Carbon::now()]);
        return $breach;
    }

    protected function nextReference(): string
    {
        $year = now()->year;
        $seq  = DataBreach::withTrashed()->whereYear('created_at', $year)->count() + 1;
        return sprintf('BR-%d-%04d', $year, $seq);
    }
}
