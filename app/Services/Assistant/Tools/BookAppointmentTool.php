<?php

namespace App\Services\Assistant\Tools;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * BookAppointmentTool — schedule a new appointment.
 * ----------------------------------------------------------------------------
 * Implements ConfirmableTool, so it is ALWAYS proposed as a confirm card before
 * booking (a wrong appointment is disruptive). Mirrors the app's own booking:
 * status 'scheduled', type in consultation/treatment/follow-up, slot check.
 */
class BookAppointmentTool implements ConfirmableTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'book_appointment';
    }

    public function description(): string
    {
        return 'Book a new appointment for a patient with a doctor on a date and time. '
             . 'Use for "book <patient> with Dr X tomorrow at 3pm". Always asks for confirmation before booking.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient'  => ['type' => 'string', 'description' => 'Patient name, phone, or ID.'],
                'date'     => ['type' => 'string', 'description' => "Date: 'today', 'tomorrow', or YYYY-MM-DD."],
                'time'     => ['type' => 'string', 'description' => "Time, e.g. '15:00' or '3pm'."],
                'doctor'   => ['type' => 'string', 'description' => 'Optional doctor name. Defaults to the current user if a doctor, else the first doctor.'],
                'duration' => ['type' => 'integer', 'description' => 'Minutes (default 30).'],
                'visit_type' => ['type' => 'string', 'enum' => ['consultation', 'treatment', 'follow-up'], 'description' => 'Defaults to consultation.'],
                'notes'    => ['type' => 'string', 'description' => 'Optional notes.'],
            ],
            'required' => ['patient', 'date', 'time'],
        ];
    }

    public function category(): string
    {
        return 'scheduling';
    }

    public function preview(array $args, User $user): string
    {
        $patient = $this->resolvePatient((string) ($args['patient'] ?? ''));
        $doctor  = $this->resolveDoctor($args['doctor'] ?? null, $user);
        [$date, $time] = $this->parseWhen($args);

        $pName = $patient?->name ?? '(unknown patient)';
        $dName = $doctor?->name ?? 'a doctor';
        $type  = $this->normalizeType($args['visit_type'] ?? null);
        $when  = $date ? Carbon::parse($date)->format('D, d M') : '(date?)';

        return "Book {$type} for {$pName} with {$dName} on {$when} at " . ($time ?: '(time?)');
    }

    public function run(array $args, User $user): array
    {
        $patient = $this->resolvePatient((string) ($args['patient'] ?? ''));
        if (!$patient) {
            return $this->patientNotFound((string) ($args['patient'] ?? ''));
        }

        $doctor = $this->resolveDoctor($args['doctor'] ?? null, $user);
        if (!$doctor) {
            return ['summary' => 'Book appointment — no doctor', 'content' => 'Couldn\'t find a doctor to book with.'];
        }

        [$date, $time] = $this->parseWhen($args);
        if (!$date || !$time) {
            return ['summary' => 'Book appointment — bad date/time', 'content' => 'I need a valid date and time to book.'];
        }

        $duration = (int) ($args['duration'] ?? 30);
        if ($duration < 10) $duration = 30;

        // Canonical booking path (Slice 5): delegate to AppointmentService so
        // the AI books through the SAME guards (blocked-slot + real interval
        // overlap, not just an exact start-time match), timeline logging and
        // transaction as the web/API. A conflict surfaces as a friendly message.
        try {
            $appt = app(\App\Services\AppointmentService::class)->create([
                'patient_id'       => $patient->id,
                'doctor_id'        => $doctor->id,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'duration_minutes' => $duration,
                'type'             => $this->normalizeType($args['visit_type'] ?? null),
                'notes'            => $args['notes'] ?? null,
            ], $user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return [
                'summary' => 'Book appointment — slot unavailable',
                'content' => $e->validator->errors()->first('appointment_time')
                    ?: "{$doctor->name} isn't available then. Please pick another time.",
            ];
        }

        return [
            'summary' => "Booked appointment for {$patient->patient_id} with {$doctor->name}",
            'content' => "Booked — {$patient->name} with {$doctor->name} on " . Carbon::parse($date)->format('D, d M Y') . " at {$time}.",
            'target'  => $appt,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{0:?string,1:?string} [date Y-m-d, time H:i] */
    protected function parseWhen(array $args): array
    {
        $date = null;
        $raw  = strtolower(trim((string) ($args['date'] ?? '')));
        if ($raw === 'today') $date = today()->toDateString();
        elseif ($raw === 'tomorrow') $date = today()->addDay()->toDateString();
        elseif ($raw !== '') {
            try { $date = Carbon::parse($raw)->toDateString(); } catch (\Throwable $e) {}
        }

        $time = null;
        if (!empty($args['time'])) {
            try { $time = Carbon::parse($args['time'])->format('H:i'); } catch (\Throwable $e) {}
        }

        return [$date, $time];
    }

    protected function normalizeType(?string $type): string
    {
        return in_array($type, ['consultation', 'treatment', 'follow-up'], true) ? $type : 'consultation';
    }

    protected function resolveDoctor(?string $name, User $user): ?User
    {
        $roles = defined(User::class . '::DOCTOR_ROLES') ? User::DOCTOR_ROLES : ['doctor'];

        $base = User::query()->where('is_active', true)
            ->where(function ($q) use ($roles) {
                $q->whereIn('role', $roles)->orWhere('name', 'like', 'Dr.%');
            });
        if (!empty($user->branch_id)) {
            $base->where('branch_id', $user->branch_id);
        }

        if (!empty($name)) {
            $doc = (clone $base)->where('name', 'like', '%' . trim($name) . '%')->first();
            if ($doc) return $doc;
        }

        // Default: the current user if they're a doctor, else the first doctor.
        if (in_array($user->role ?? '', $roles, true) || str_starts_with((string) $user->name, 'Dr.')) {
            return $user;
        }

        return $base->orderBy('name')->first();
    }
}
