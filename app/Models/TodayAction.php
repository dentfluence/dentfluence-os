<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TodayAction — one materialised item in the Today's Actions projection
 * (Phase 1 · Workstream E). This is a derived read model rebuilt by
 * TodayActionsProjector; it is never edited by hand and carries no business
 * logic. See the create_today_actions_table migration.
 *
 * @property string      $category
 * @property string      $priority
 * @property int|null    $patient_id
 * @property int|null    $lead_id
 * @property int|null    $relationship_id
 * @property string      $patient_name
 * @property string|null $reason
 * @property string|null $suggested_action
 * @property string|null $link
 * @property array|null  $meta
 * @property \Carbon\Carbon|null $generated_at
 */
class TodayAction extends Model
{
    protected $table = 'today_actions';

    protected $fillable = [
        'category',
        'priority',
        'patient_id',
        'lead_id',
        'relationship_id',
        'patient_name',
        'reason',
        'suggested_action',
        'link',
        'meta',
        'generated_at',
    ];

    protected $casts = [
        'meta'         => 'array',
        'generated_at' => 'datetime',
    ];
}
