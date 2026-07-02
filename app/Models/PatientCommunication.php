<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'type',
        'direction',
        'is_auto',
        'status',
        'subject',
        'message',
        'scheduled_at',
        'sent_at',
        'created_by',
        'staff_name',
    ];

    protected $casts = [
        'is_auto'      => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────────── */

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Helpers ───────────────────────────────────────── */

    /**
     * Human-readable channel icon (for Blade use).
     */
    public function typeIcon(): string
    {
        return match($this->type) {
            'call'      => '📞',
            'whatsapp'  => '💬',
            'email'     => '✉️',
            'sms'       => '📱',
            default     => '📋',
        };
    }

    /**
     * Is this a future scheduled communication?
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at && $this->scheduled_at->isFuture();
    }
}
