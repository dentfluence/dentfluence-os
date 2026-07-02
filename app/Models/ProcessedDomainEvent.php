<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for domain-event handling — Phase 0.
 *
 * One row per (event_id, subscriber). Its presence means "this subscriber has
 * already processed this event" so re-delivery (now, or under a future async
 * transport) is safely ignored.
 *
 * @property string $event_id
 * @property string $subscriber
 * @property string $event_name
 */
class ProcessedDomainEvent extends Model
{
    protected $table = 'processed_domain_events';

    protected $fillable = [
        'event_id',
        'subscriber',
        'event_name',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
