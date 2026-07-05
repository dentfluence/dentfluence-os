<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * MessageTemplate — user-editable in-app message templates.
 *
 * NOT to be confused with config/whatsapp.php, which holds Meta-approved
 * WhatsApp Business templates (gated by Meta's template-approval process,
 * using {{1}}, {{2}} numbered placeholders). This model is the simpler,
 * fully in-app concept: free-text body stored in the DB with human-readable
 * <TokenName> placeholders that get string-replaced at send time by
 * renderBody() below. Any feature (recall, birthday, missed-calls, etc.)
 * can point at a template row and call renderBody() to get final text.
 *
 * Token syntax: angle-bracket tokens, e.g. <PatientName>. See TOKENS for
 * the canonical, supported list — do not invent new tokens in Blade/JS
 * without adding them here first, since renderBody() only fills what's
 * declared here (and whatever the caller passes in $tokens).
 */
class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'type',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Token system ─────────────────────────────────────────────────────────

    /** Delimiter-wrapped token name used in template bodies, e.g. <PatientName>. */
    public const TOKEN_OPEN  = '<';
    public const TOKEN_CLOSE = '>';

    /**
     * Canonical list of tokens this system supports, grouped by the data
     * they come from. Keys are the token name (without delimiters); values
     * are a short human label shown in the editor's token picker.
     *
     * Only tokens that are cheaply available from Patient/Appointment/
     * AppSetting at send time are listed here — don't add a token unless
     * there's a real, cheap source for it.
     */
    public const TOKENS = [
        'PatientName'      => 'Patient full name',
        'PatientFirstName' => "Patient's first name",
        'ClinicName'       => 'Clinic name',
        'ContactNumber'    => "Patient's phone number",
        'AppointmentDate'  => 'Appointment date (next/relevant appointment)',
        'RecallReason'     => 'Why the recall was triggered (e.g. treatment/procedure name)',
        'Age'              => "Patient's age (birthday recall)",
    ];

    /** All supported token names, without delimiters. */
    public static function tokenNames(): array
    {
        return array_keys(self::TOKENS);
    }

    /**
     * Replace <TokenName> placeholders in the template body with values
     * from $tokens (keyed by token name, no delimiters). Any recognised
     * token not present in $tokens is replaced with an empty string rather
     * than left dangling in the sent message.
     */
    public function renderBody(array $tokens = []): string
    {
        $body = $this->body ?? '';

        foreach (self::tokenNames() as $token) {
            $value = $tokens[$token] ?? '';
            $body  = str_replace(self::TOKEN_OPEN . $token . self::TOKEN_CLOSE, $value, $body);
        }

        return $body;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }
}
