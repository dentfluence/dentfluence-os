<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabCase — represents a dental lab work order sent to an external lab.
 *
 * Statuses: sent | in_progress | received | rejected
 * Work types: crown_bridge | denture | implant | ortho
 */
class LabCase extends Model
{
    protected $fillable = [
        'case_number',
        'branch_id',
        'patient_id',
        'doctor_id',
        'work_type',
        'work_subtype',
        'tooth_number',
        'shade',
        'lab_vendor',
        'lab_cost',
        'sent_date',
        'expected_return_date',
        'received_date',
        'status',
        'instructions',
        'notes',
    ];

    // ── Boot — auto-fill system fields on create ────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $case) {
            // Auto-generate case number  e.g. LAB-2026-0001
            if (empty($case->case_number)) {
                $year  = now()->format('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $case->case_number = 'LAB-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // branch_id — inherit from logged-in user, fall back to 1
            if (empty($case->branch_id)) {
                $case->branch_id = auth()->user()?->branch_id ?? 1;
            }

        });
    }

    protected $casts = [
        'sent_date'            => 'date',
        'expected_return_date' => 'date',
        'received_date'        => 'date',
        'lab_cost'             => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Human-readable work type label */
    public function workTypeLabel(): string
    {
        return [
            'crown_bridge' => 'Crown / Bridge',
            'denture'      => 'Denture / Partial',
            'implant'      => 'Implant Component',
            'ortho'        => 'Orthodontic / Aligner',
        ][$this->work_type] ?? ucfirst($this->work_type);
    }

    /** Badge colour class for status */
    public function statusColor(): string
    {
        return [
            'sent'        => 'bg-blue-100 text-blue-700',
            'in_progress' => 'bg-yellow-100 text-yellow-700',
            'received'    => 'bg-green-100 text-green-700',
            'rejected'    => 'bg-red-100 text-red-700',
        ][$this->status] ?? 'bg-gray-100 text-gray-700';
    }

    /** Subtypes available per work type */
    public static function subtypesFor(string $workType): array
    {
        return [
            'crown_bridge' => ['PFM', 'Zirconia', 'All-Ceramic (Emax)', 'Metal', 'Temporary'],
            'denture'      => ['Full Denture', 'Partial Denture (Metal)', 'Partial Denture (Acrylic)', 'Flexible Denture', 'Immediate Denture'],
            'implant'      => ['Implant Crown', 'Custom Abutment', 'Stock Abutment', 'Bar Overdenture'],
            'ortho'        => ['Study Model', 'Retainer (Hawley)', 'Retainer (Essix)', 'Night Guard', 'Aligner'],
        ][$workType] ?? [];
    }
}
