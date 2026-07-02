<?php
// ─── app/Models/Tag.php ───────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'name', 'slug', 'color', 'bg_color', 'group',
        'description', 'branch_id', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'patient_tag')
                    ->withPivot('added_by')
                    ->withTimestamps();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForBranch($query, $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('group', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getPatientsCountAttribute(): int
    {
        return $this->patients()->count();
    }

    // ── Mutators ──────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Returns all distinct groups, ordered.
     */
    public static function allGroups(): array
    {
        return static::select('group')
                     ->distinct()
                     ->orderBy('group')
                     ->pluck('group')
                     ->toArray();
    }

    /**
     * Grouped for the tag picker dropdown.
     * Returns: ['Financial' => [Tag, Tag], 'Behavior' => [...], ...]
     */
    public static function groupedForPicker(?int $branchId = null): \Illuminate\Support\Collection
    {
        $query = static::orderBy('group')->orderBy('sort_order')->orderBy('name');
        if ($branchId) {
            $query->forBranch($branchId);
        }
        return $query->get()->groupBy('group');
    }
}


// ─── Add to app/Models/Patient.php ───────────────────────────────────────
// Add this relationship method inside the Patient class:

/*
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'patient_tag')
                    ->withPivot('added_by')
                    ->withTimestamps();
    }
*/
