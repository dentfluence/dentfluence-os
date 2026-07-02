<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * PatientBalanceTool — what a patient owes, plus recent payments.
 * Read-only (financial category — still a read, no money is moved).
 */
class PatientBalanceTool implements AssistantTool
{
    use ResolvesPatient;

    public function name(): string
    {
        return 'patient_balance';
    }

    public function description(): string
    {
        return 'Get a patient\'s billing summary: total billed, total received, outstanding balance, '
             . 'and their most recent payments. Use for "what does X owe" or "show X\'s payments".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'patient' => [
                    'type'        => 'string',
                    'description' => 'Patient name, phone, or patient ID.',
                ],
            ],
            'required' => ['patient'],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $query   = (string) ($args['patient'] ?? '');
        $patient = $this->resolvePatient($query);

        if (!$patient) {
            return $this->patientNotFound($query);
        }

        $lines = [];
        $lines[] = "BILLING — {$patient->name} ({$patient->patient_id})";
        $lines[] = "Total billed:    " . $this->money($patient->total_billed);
        $lines[] = "Total received:  " . $this->money($patient->total_received);
        $lines[] = "Outstanding:     " . $this->money($patient->outstanding_balance);

        // Recent payments (guarded — model may differ across installs).
        if (class_exists(\App\Models\InvoicePayment::class)) {
            try {
                $payments = \App\Models\InvoicePayment::where('patient_id', $patient->id)
                    ->latest('payment_date')->limit(5)
                    ->get(['amount', 'payment_date', 'payment_mode', 'reference_no']);

                if ($payments->isNotEmpty()) {
                    $lines[] = "";
                    $lines[] = "Recent payments:";
                    foreach ($payments as $pay) {
                        $when = optional($pay->payment_date)->format('d M Y') ?: '';
                        $lines[] = "- " . $this->money($pay->amount) . " on {$when} ({$pay->payment_mode})";
                    }
                }
            } catch (\Throwable $e) {
                // Silently skip payment detail if the table/columns differ.
            }
        }

        return [
            'summary' => "Read billing for {$patient->patient_id} (balance " . $this->money($patient->outstanding_balance) . ")",
            'content' => implode("\n", $lines),
            'target'  => $patient,
        ];
    }
}
