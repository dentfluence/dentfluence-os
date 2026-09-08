<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\HrAttendance;
use App\Models\HrEntryExitLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HrAttendanceController (API v1) — M-16, Android V1.1.
 *
 * The one job where the phone beats the web: a staffer marks THEIR OWN
 * arrival and departure. Three calls, nothing else:
 *
 *   GET  /api/v1/hr/attendance/today
 *   POST /api/v1/hr/attendance/check-in
 *   POST /api/v1/hr/attendance/check-out
 *
 * RULES (CEO, 8 Sep 2026):
 *   - self only. The user on the token is the only person these routes can
 *     mark; there is no user_id in the body and none is read. Marking someone
 *     else, corrections and bulk marking stay on the web (module:hr,edit).
 *   - no location check in V1.1.
 *   - auth only, no module gate — a receptionist with no HR permission still
 *     has to be able to say "I am here". This is what GET /hr/scan was left
 *     public for; with this route the phone never needs that page.
 *
 * Writes the SAME two rows the QR scan writes (HrFinanceController::logScan):
 * an hr_entry_exit_logs row (method 'manual' — the migration's enum has no
 * 'app' value, and adding one is a migration V1.1 does not need) and the
 * hr_attendance row for today, so every web report keeps reading one table.
 */
class HrAttendanceController extends ApiController
{
    public function today(Request $request): JsonResponse
    {
        return $this->success($this->payload($request), 'Attendance today');
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $row  = $user->hrAttendance()->whereDate('date', today())->first();

        if ($row && $row->check_in) {
            return $this->error('Already checked in today at ' . $row->check_in . '.', [], 422);
        }

        DB::transaction(function () use ($user, $request) {
            HrEntryExitLog::create([
                'user_id'    => $user->id,
                'type'       => 'entry',
                'logged_at'  => now(),
                'method'     => 'manual',
                'ip_address' => $request->ip(),
                'notes'      => 'mobile self check-in',
            ]);

            $user->hrAttendance()->updateOrCreate(
                ['date' => today()],
                [
                    'status'          => HrAttendance::STATUS_PRESENT,
                    'check_in'        => now()->format('H:i'),
                    'check_in_method' => 'manual',
                ]
            );
        });

        return $this->success($this->payload($request), 'Checked in.');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $row  = $user->hrAttendance()->whereDate('date', today())->first();

        if (! $row || ! $row->check_in) {
            return $this->error('Check in first.', [], 422);
        }
        if ($row->check_out) {
            return $this->error('Already checked out today at ' . $row->check_out . '.', [], 422);
        }

        DB::transaction(function () use ($user, $row, $request) {
            HrEntryExitLog::create([
                'user_id'    => $user->id,
                'type'       => 'exit',
                'logged_at'  => now(),
                'method'     => 'manual',
                'ip_address' => $request->ip(),
                'notes'      => 'mobile self check-out',
            ]);

            $row->update([
                'check_out'        => now()->format('H:i'),
                'check_out_method' => 'manual',
            ]);
        });

        return $this->success($this->payload($request), 'Checked out.');
    }

    private function payload(Request $request): array
    {
        $row = $request->user()->hrAttendance()->whereDate('date', today())->first();

        $in  = $row?->check_in  ? substr((string) $row->check_in, 0, 5) : null;
        $out = $row?->check_out ? substr((string) $row->check_out, 0, 5) : null;

        return [
            'date'          => today()->toDateString(),
            'status'        => $row?->status ?? HrAttendance::STATUS_ABSENT,
            'check_in'      => $in,
            'check_out'     => $out,
            'hours_worked'  => $row?->hours_worked,
            'can_check_in'  => $in === null,
            'can_check_out' => $in !== null && $out === null,
        ];
    }
}
