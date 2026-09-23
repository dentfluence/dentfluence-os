<?php

namespace App\Services\Communication;

use App\Models\Patient;
use App\Services\Relationship\CommunicationGuard;
use App\Support\Features\Feature;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppLinkService — single source of truth for click-to-chat (wa.me) sends.
 *
 * Until the WhatsApp Cloud API (Meta) is approved, every transactional send in
 * Dentfluence — appointment reminders, review requests, recalls, birthdays,
 * one-off messages — is a click-to-chat deep link. This service centralizes the
 * three things that were previously duplicated (inconsistently) across the
 * prescription controller, the appointments page and the Blade button:
 *
 *   1. Phone normalization (E.164 / India +91).
 *   2. DPDP consent + do-not-contact gating (reuses CommunicationGuard, the same
 *      shadow-then-enforce pattern the prescription send uses).
 *   3. Message copy (config-driven templates, locale-ready).
 *
 * When 'mode' flips from 'web_open' to 'api', callers stay unchanged — only the
 * internals of url()/resolve() change.
 */
class WhatsAppLinkService
{
    /**
     * Contexts whose recipient is a business, not a patient. These skip the
     * "Hi there" fallback and are never subject to patient consent — a lab
     * waiting on work instructions has not opted in to anything, and does not
     * need to.
     */
    public const BUSINESS_CONTEXTS = ['lab_instructions', 'purchase_order'];

    public function __construct(private CommunicationGuard $guard)
    {
    }

    /**
     * Normalize a raw phone to WhatsApp-ready digits. 10-digit local numbers get
     * the configured country code prepended. Returns null if there are no digits.
     */
    public function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        if ($digits === '') {
            return null;
        }

        $cc = (string) config('communication.whatsapp.country_code', '91');

        if (strlen($digits) === 10) {
            $digits = $cc . $digits;
        }

        return $digits;
    }

    /**
     * Build a wa.me deep link. A null/empty phone yields a "generic share" link
     * (opens WhatsApp with the text but no recipient) — same fallback the
     * prescription send already relied on.
     */
    public function url(?string $phone, ?string $message = null): string
    {
        $base = rtrim((string) config('communication.whatsapp.web_url', 'https://wa.me/'), '/') . '/';
        $num  = $this->normalizePhone($phone) ?? '';
        $text = ($message !== null && $message !== '') ? '?text=' . rawurlencode($message) : '';

        return $base . $num . $text;
    }

    /**
     * Render the message copy for a context from config templates. Unknown
     * contexts (or the 'generic' context) fall back to a caller-supplied
     * 'message' in $data, so a free-text send always works.
     */
    public function render(string $context, array $data = []): string
    {
        $template = config("communication.whatsapp.templates.{$context}");

        if (! is_string($template) || $template === '') {
            return (string) ($data['message'] ?? '');
        }

        $data['clinic'] = $data['clinic'] ?? config('app.clinic_name', 'our clinic');

        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Normalize caller-supplied display params into what the templates expect.
     * Shared by the web and API link controllers so their message copy is
     * identical. Client-supplied values win; this only fills gaps and reshapes
     * a bare doctor name into the " with Dr. X" fragment the appointment
     * templates embed.
     */
    public function prepareParams(string $context, ?Patient $patient, array $params = [], ?string $message = null): array
    {
        // A lab and a dealer are businesses. "Hi there" in front of a purchase
        // order reads as a mail-merge that went wrong, so the friendly default
        // applies only to messages actually addressed to a patient.
        $isBusiness = in_array($context, self::BUSINESS_CONTEXTS, true);

        $params['patient'] = $params['patient']
            ?? $patient?->name
            ?? ($isBusiness ? '' : 'there');

        if ($message !== null && $message !== '') {
            $params['message'] = $message;
        }

        if ($context === 'review_request' && empty($params['review_url'])) {
            $params['review_url'] = config('communication.whatsapp.review_url');
        }

        if (! empty($params['doctor'])) {
            $name = ltrim((string) $params['doctor']);
            $params['doctor'] = str_starts_with(strtolower($name), 'dr')
                ? " with {$name}"
                : " with Dr. {$name}";
        } else {
            $params['doctor'] = '';
        }

        // Two shapes of the same fact. {treatment} is a fragment that slots
        // mid-sentence (" for aligners") and {treatment_plain} is the bare
        // word for templates that name it directly. Both render EMPTY when
        // the treatment is unknown, so a message never goes out reading
        // "your appointment for ." — a dangling label is worse than a
        // slightly vaguer sentence.
        $treatment = trim((string) ($params['treatment'] ?? ''));
        $params['treatment_plain'] = $treatment !== '' ? $treatment : 'treatment';
        $params['treatment']       = $treatment !== '' ? " for {$treatment}" : '';

        // Every optional placeholder gets an empty default. A template that
        // renders "Due: {due_date}" because nobody passed one is worse than a
        // template with the line simply absent — and it is the sort of thing
        // that only shows up after it has gone to a real vendor.
        foreach (['old_date', 'case_number', 'work', 'due_date', 'po_number', 'items'] as $k) {
            $params[$k] = trim((string) ($params[$k] ?? ''));
        }

        // Instructions get their own newline only when there ARE instructions,
        // so an empty one does not leave a blank line mid-message.
        $instructions = trim((string) ($params['instructions'] ?? ''));
        $params['instructions'] = $instructions !== '' ? $instructions : '';

        // The call-us line, from one config value. Appended as a whole
        // sentence rather than a bare number so a clinic with no number
        // configured simply gets a shorter message, not a broken one.
        if (! array_key_exists('contact', $params)) {
            $phone = trim((string) config('communication.whatsapp.contact_phone', ''));
            $params['contact'] = $phone !== ''
                ? "\nFor any assistance, call us on {$phone}."
                : '';
        }

        return $params;
    }

    /**
     * Consent + do-not-contact decision, mirroring the prescription send exactly:
     * both checks always evaluate (so they can be observed in logs), but only
     * block when their feature flag is on — otherwise it's shadow-logged and the
     * send proceeds. Keeps today's behaviour unchanged until the flags flip.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function guardDecision(?Patient $patient, string $type = 'service'): array
    {
        // DPDP consent (gated by guard.consent_required)
        $consent = $this->guard->hasWhatsAppConsent($patient, $type);

        if (! $consent['allowed']) {
            Log::info('WhatsApp link blocked by consent check (shadow unless guard.consent_required is on)', [
                'patient_id' => $patient?->id,
                'reason'     => $consent['reason'],
            ]);

            if (Feature::enabled('guard.consent_required')) {
                return ['allowed' => false, 'reason' => $consent['reason'] ?? 'WhatsApp consent required before sending.'];
            }
        }

        // Do-not-contact + channel eligibility (gated by guard.full_8factor)
        if ($patient && $patient->relationship_id) {
            $check = $this->guard->checkDoNotContactAndChannel($patient->relationship_id, 'whatsapp');

            if (! $check['allowed']) {
                Log::info('WhatsApp link blocked by CommunicationGuard (shadow unless guard.full_8factor is on)', [
                    'patient_id' => $patient->id,
                    'reason'     => $check['reason'],
                ]);

                if (Feature::enabled('guard.full_8factor')) {
                    return [
                        'allowed' => false,
                        'reason'  => match ($check['reason']) {
                            'do_not_contact'     => 'This patient has asked not to be contacted.',
                            'channel_ineligible' => 'No phone number on file for WhatsApp.',
                            default              => 'This message was blocked by the communication guard.',
                        },
                    ];
                }
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * High-level resolve — the one method controllers should call. Runs the
     * guard, renders the message, builds the link.
     *
     * @return array{allowed: bool, url: ?string, phone: ?string, reason: ?string}
     */
    public function resolve(?Patient $patient, ?string $phone, string $context, array $data = [], string $type = 'service'): array
    {
        $decision = $this->guardDecision($patient, $type);

        if (! $decision['allowed']) {
            return ['allowed' => false, 'url' => null, 'phone' => null, 'reason' => $decision['reason']];
        }

        $phone   = $phone ?: $patient?->phone;
        $message = $this->render($context, $data);

        return [
            'allowed' => true,
            'url'     => $this->url($phone, $message),
            'phone'   => $this->normalizePhone($phone),
            'reason'  => null,
        ];
    }
}
