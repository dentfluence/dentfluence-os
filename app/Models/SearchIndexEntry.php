<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SearchIndexEntry — one row of the Search Engine projection
 * (Phase 6 · Slice 3). Derived/disposable — rebuilt by SearchIndexProjector
 * from the `relationships` table, never written by hand and never a source
 * of truth. See create_search_index_table migration.
 *
 * @property int         $relationship_id
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $email
 * @property int         $score
 * @property string|null $status
 * @property string|null $patient_name
 * @property string|null $link
 */
class SearchIndexEntry extends Model
{
    protected $table = 'search_index';

    protected $fillable = [
        'relationship_id',
        'name',
        'phone',
        'email',
        'score',
        'status',
        'source',
        'patient_name',
        'link',
        'computed_at',
        'generated_at',
    ];

    protected $casts = [
        'score'        => 'integer',
        'computed_at'  => 'datetime',
        'generated_at' => 'datetime',
    ];
}
