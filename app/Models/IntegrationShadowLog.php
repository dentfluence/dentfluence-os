<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IntegrationShadowLog — one dual-run comparison row from Phase 7's
 * IntegrationEngine. Purely observational, read by the `integration:parity`
 * report only. See App\Integration\IntegrationEngine.
 *
 * @property int         $id
 * @property string       $provider          whatsapp|google|meta|website|abdm|payments
 * @property string       $method            text|template
 * @property string       $action            legacy|cutover
 * @property bool|null    $agreed
 * @property array|null   $preview_payload
 * @property array|null   $result_payload
 * @property string|null  $notes
 */
class IntegrationShadowLog extends Model
{
    // Explicit — matches this codebase's existing "*_shadow_log" singular
    // table naming convention (workflow_shadow_log, automation_shadow_log).
    protected $table = 'integration_shadow_log';

    protected $fillable = [
        'provider',
        'method',
        'action',
        'agreed',
        'preview_payload',
        'result_payload',
        'notes',
    ];

    protected $casts = [
        'agreed'          => 'boolean',
        'preview_payload' => 'array',
        'result_payload'  => 'array',
    ];
}
