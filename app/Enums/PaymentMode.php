<?php

namespace App\Enums;

/**
 * PaymentMode — the ONE canonical set of payment modes for the whole
 * application (web, mobile API, receipts, finance).
 *
 * FROZEN VOCABULARY (approved 2026-08-19). Nine modes, no more:
 *
 *   cash           Cash
 *   card           Credit Card    <- the stored value stays 'card' for history;
 *                                   it means CREDIT CARD and must never be
 *                                   displayed as a bare "Card".
 *   debit_card     Debit Card
 *   upi            UPI
 *   cheque         Cheque
 *   bank_transfer  Bank Transfer
 *   emi            EMI            <- single-invoice only (provider/clinic EMI)
 *   wallet         Wallet         <- system-written tender, never staff-selectable
 *   other          Other
 *
 * REMOVED 2026-08-19: `netbanking`. It duplicated `bank_transfer` with no
 * behavioural difference. Historical rows were migrated to `bank_transfer` by
 * 2026_08_19_100001; the value is no longer storable.
 *
 * NOT part of this vocabulary: `insurance`. It survives in the
 * finance_transactions ENUM for historical safety only — zero writers exist and
 * none may be added. It is deliberately absent from every list below.
 *
 * Before this enum existed the vocabulary was duplicated across seven literal
 * arrays in controllers, services and Blade. That duplication is exactly how
 * finance_transactions drifted out of sync with invoice_payments for two and a
 * half months (A3 audit). There is no second list. Add a mode here or nowhere.
 */
enum PaymentMode: string
{
    case Cash         = 'cash';
    case Card         = 'card';          // CREDIT CARD
    case DebitCard    = 'debit_card';
    case Upi          = 'upi';
    case Cheque       = 'cheque';
    case BankTransfer = 'bank_transfer';
    case Emi          = 'emi';
    case Wallet       = 'wallet';
    case Other        = 'other';

    /**
     * The user-facing label. Title Case; screens that want shouting (printed
     * receipts) apply strtoupper() to this, which yields "CREDIT CARD".
     *
     * `card` MUST read "Credit Card" everywhere. Never "Card", never
     * "Credit/Debit Card".
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash         => 'Cash',
            self::Card         => 'Credit Card',
            self::DebitCard    => 'Debit Card',
            self::Upi          => 'UPI',
            self::Cheque       => 'Cheque',
            self::BankTransfer => 'Bank Transfer',
            self::Emi          => 'EMI',
            self::Wallet       => 'Wallet',
            self::Other        => 'Other',
        };
    }

    /** Every canonical value, as strings. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Label for a raw stored string, including values this enum no longer
     * accepts. Historical rows must still render: a pre-migration row, an
     * `insurance` finance row, or a NULL renders sensibly rather than blank.
     *
     * This is the ONLY correct way to display a payment mode. Do not use
     * ucfirst(str_replace('_',' ', $mode)) — it renders 'card' as "Card".
     */
    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $mode = self::tryFrom($value);

        if ($mode !== null) {
            return $mode->label();
        }

        // Legacy / retired values. Kept renderable, never selectable.
        return match ($value) {
            'netbanking' => 'Bank Transfer',   // retired 2026-08-19
            'insurance'  => 'Insurance',       // historical finance rows only
            default      => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    /**
     * Options for a <select>, in the order staff should see them.
     *
     * @param  array<int,string>  $except  values to omit (e.g. 'wallet', which
     *                                     is system-written and never chosen)
     * @return array<int,array{value:string,label:string}>
     */
    public static function options(array $except = []): array
    {
        $out = [];

        foreach (self::cases() as $mode) {
            if (in_array($mode->value, $except, true)) {
                continue;
            }
            $out[] = ['value' => $mode->value, 'label' => $mode->label()];
        }

        return $out;
    }

    /**
     * A Laravel `in:` validation rule built from the canonical set.
     *
     * Always prefer this over a hand-written list — a hand-written list is how
     * the vocabulary drifts.
     *
     * @param  array<int,string>  $except
     */
    public static function rule(array $except = []): string
    {
        $allowed = array_values(array_diff(self::values(), $except));

        return 'in:' . implode(',', $allowed);
    }
}
