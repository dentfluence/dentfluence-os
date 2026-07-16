<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * GuardsKnowledgeBankPurity
 * -------------------------
 * Enforces the permanent ownership boundary (frozen architecture §2/§5.1):
 * Knowledge Bank models are GLOBAL, versioned education and must NEVER carry
 * commerce (price/discount/brand) or patient/clinic data. Pricing lives in the
 * Treatment Module; PHI lives in the clinic-scoped Media Library / patient
 * records. If a forbidden attribute ever gets set on a KB model, we fail loud
 * at save time rather than silently leaking money or PHI into global IP.
 *
 * This is a design guard, not a security control — it catches developer error.
 */
trait GuardsKnowledgeBankPurity
{
    /** Substrings that must never appear in a Knowledge Bank attribute name. */
    protected static array $forbiddenKbAttributeFragments = [
        'price', 'discount', 'cost', 'brand', 'clinic_id', 'patient_id', 'consent_ref',
    ];

    public static function bootGuardsKnowledgeBankPurity(): void
    {
        static::saving(function ($model): void {
            foreach (array_keys($model->getAttributes()) as $attribute) {
                foreach (static::$forbiddenKbAttributeFragments as $fragment) {
                    if (str_contains($attribute, $fragment)) {
                        throw new LogicException(sprintf(
                            'Knowledge Bank model [%s] must not carry commerce/PHI attribute [%s]. '
                            . 'Pricing belongs to the Treatment Module; PHI to the clinic layer.',
                            static::class,
                            $attribute
                        ));
                    }
                }
            }
        });
    }
}
