<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletCampaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'amount',
        'expiry_date',
        'applicable_treatments',
        'filter_gender',
        'filter_area',
        'filter_tag_ids',
        'filter_age_min',
        'filter_age_max',
        'filter_membership',
        'filter_source',
        'status',
        'patients_credited',
        'total_amount_issued',
        'applied_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'total_amount_issued'    => 'decimal:2',
        'expiry_date'            => 'date',
        'applied_at'             => 'datetime',
        'applicable_treatments'  => 'array',
        'filter_gender'          => 'array',
        'filter_area'            => 'array',
        'filter_tag_ids'         => 'array',
        'filter_membership'      => 'array',
        'filter_source'          => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isApplied(): bool  { return $this->status === 'applied'; }

    /**
     * Build the patient query based on the campaign's filter criteria.
     * Returns an Eloquent Builder — can be used for count() or get().
     */
    public function matchingPatientsQuery(): Builder
    {
        // SoftDeletes handles exclusion of deleted patients automatically
        $query = Patient::query();

        // ── Gender filter ────────────────────────────────────────────────────
        if (! empty($this->filter_gender)) {
            $query->whereIn('gender', $this->filter_gender);
        }

        // ── Area filter ──────────────────────────────────────────────────────
        if (! empty($this->filter_area)) {
            $query->whereIn('area', $this->filter_area);
        }

        // ── Tag filter (patient must have ALL specified tags) ────────────────
        if (! empty($this->filter_tag_ids)) {
            foreach ($this->filter_tag_ids as $tagId) {
                $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
            }
        }

        // ── Age filter (uses DOB if available, falls back to age_years) ──────
        if ($this->filter_age_min || $this->filter_age_max) {
            $query->where(function ($q) {
                // Patients with known DOB
                $q->where(function ($dob) {
                    $dob->where('dob_unknown', false)
                        ->whereNotNull('date_of_birth');
                    if ($this->filter_age_min) {
                        $dob->where('date_of_birth', '<=', now()->subYears($this->filter_age_min));
                    }
                    if ($this->filter_age_max) {
                        $dob->where('date_of_birth', '>=', now()->subYears($this->filter_age_max + 1)->addDay());
                    }
                })
                // Patients with only age_years
                ->orWhere(function ($age) {
                    $age->where('dob_unknown', true)->whereNotNull('age_years');
                    if ($this->filter_age_min) {
                        $age->where('age_years', '>=', $this->filter_age_min);
                    }
                    if ($this->filter_age_max) {
                        $age->where('age_years', '<=', $this->filter_age_max);
                    }
                });
            });
        }

        // ── Membership filter ────────────────────────────────────────────────
        if (! empty($this->filter_membership)) {
            $query->whereIn('membership_status', $this->filter_membership);
        }

        // ── Source filter ────────────────────────────────────────────────────
        if (! empty($this->filter_source)) {
            $query->whereIn('source', $this->filter_source);
        }

        return $query;
    }

    /**
     * Count patients who match this campaign's filters.
     */
    public function matchingPatientCount(): int
    {
        return $this->matchingPatientsQuery()->count();
    }

    /**
     * Human-readable summary of filters applied.
     */
    public function filterSummary(): string
    {
        $parts = [];
        if (! empty($this->filter_gender))     $parts[] = 'Gender: ' . implode(', ', $this->filter_gender);
        if (! empty($this->filter_area))        $parts[] = 'Area: ' . implode(', ', $this->filter_area);
        if ($this->filter_age_min || $this->filter_age_max) {
            $parts[] = 'Age: ' . ($this->filter_age_min ?? '0') . '–' . ($this->filter_age_max ?? '∞');
        }
        if (! empty($this->filter_membership))  $parts[] = 'Membership: ' . implode(', ', $this->filter_membership);
        if (! empty($this->filter_tag_ids))     $parts[] = count($this->filter_tag_ids) . ' tag(s)';
        if (! empty($this->filter_source))      $parts[] = 'Source: ' . implode(', ', $this->filter_source);
        return $parts ? implode(' · ', $parts) : 'All active patients';
    }
}
