<?php

namespace App\Models\Prescription;

use App\Traits\HashChained;
use Illuminate\Database\Eloquent\Model;

class PrescriptionAuditLog extends Model
{
    use HashChained; // tamper-evident: hash-chained + append-only (Phase A)

    protected $table = 'prescription_audit_logs';
    protected $fillable = ['prescription_id', 'user_id', 'action', 'notes', 'snapshot'];
    protected $casts = ['snapshot' => 'array'];

    public function prescription() { return $this->belongsTo(Prescription::class); }
    public function user()         { return $this->belongsTo(\App\Models\User::class); }
}
