<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LabPrescriptionTemplate — reusable clinical prescription preset.
 *
 * Doctors create templates (e.g. "Posterior Zirconia") and apply them
 * when creating a lab case. All fields fill automatically.
 */
class LabPrescriptionTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'category', 'subtype',
        'material', 'shade', 'clinical_fields', 'notes',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'clinical_fields' => 'array',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForCategory($q, string $category)
    {
        return $q->where(function ($w) use ($category) {
            $w->where('category', $category)->orWhereNull('category');
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Return the fields needed to pre-fill a prescription form.
     */
    public function toPreset(): array
    {
        return [
            'template_id'     => $this->id,
            'template_name'   => $this->name,
            'material'        => $this->material,
            'shade'           => $this->shade,
            'clinical_fields' => $this->clinical_fields ?? [],
        ];
    }
}
