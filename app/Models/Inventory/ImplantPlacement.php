<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Patient;

class ImplantPlacement extends Model
{
    use SoftDeletes;

    protected $table = 'implant_placements';

    protected $fillable = [
        'patient_id', 'treatment_visit_id', 'implant_catalog_id', 'surgeon_id',
        'lot_number', 'serial_number', 'tooth_position', 'surgery_date',
        'implant_brand_freetext', 'implant_code_freetext',
        'label_photo_path', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'surgery_date' => 'date',
    ];

    /* ── Relationships ── */

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatmentVisit(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TreatmentVisit::class, 'treatment_visit_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ImplantCatalog::class, 'implant_catalog_id');
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Helpers ── */

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'placed'           => 'Placed',
            'osseointegrating' => 'Osseointegrating',
            'loaded'           => 'Loaded',
            'failed'           => 'Failed',
            'explanted'        => 'Explanted',
            default            => 'Unknown',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'placed'           => '#1a5ea8',
            'osseointegrating' => '#a05c00',
            'loaded'           => '#1a7a45',
            'failed'           => '#b52020',
            'explanted'        => '#888',
            default            => '#555',
        };
    }

    public function getImplantName(): string
    {
        if ($this->catalogItem) {
            return $this->catalogItem->getFullName();
        }
        return implode(' ', array_filter([
            $this->implant_brand_freetext,
            $this->implant_code_freetext,
        ])) ?: 'Implant';
    }
}
