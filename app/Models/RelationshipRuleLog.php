<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Decision Log row (Phase 0 foundation) — owned by the Rules Engine.
 *
 * Records WHY the automation brain decided what it did, for debugging, audit,
 * AI explanation and future analytics. Distinct from the Activity ledger
 * (which records what happened to the patient).
 *
 * The base columns (rule_name, relationship_id, subject_*, fired_at, metadata)
 * pre-existed; Phase 0 added the nullable inputs/conditions/result/decision/
 * requesting_engine/user_id columns.
 */
class RelationshipRuleLog extends Model
{
    protected $table = 'relationship_rule_logs';

    protected $fillable = [
        'rule_name',
        'relationship_id',
        'subject_type',
        'subject_id',
        'fired_at',
        'metadata',
        // Phase 0 Decision Log columns:
        'inputs',
        'conditions',
        'result',
        'decision',
        'requesting_engine',
        'user_id',
    ];

    protected $casts = [
        'fired_at'   => 'datetime',
        'metadata'   => 'array',
        'inputs'     => 'array',
        'conditions' => 'array',
    ];
}
