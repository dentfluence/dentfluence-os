<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 0 — Feature-flag override row.
 *
 * A row here OVERRIDES the default in config/features.php for a given scope.
 * branch_id = null means a global override; otherwise it is clinic-scoped.
 *
 * @property string   $key
 * @property int|null $branch_id
 * @property bool     $enabled
 * @property string|null $note
 */
class FeatureFlag extends Model
{
    protected $table = 'feature_flags';

    protected $fillable = [
        'key',
        'branch_id',
        'enabled',
        'note',
    ];

    protected $casts = [
        'enabled'   => 'boolean',
        'branch_id' => 'integer',
    ];
}
