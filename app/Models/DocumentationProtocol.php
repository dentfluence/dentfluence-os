<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 7G — DocumentationProtocol Model
 *
 * A protocol defines the set of clinical files required for a procedure type.
 * e.g. "Root Canal Protocol" → requires Pre-op X-ray, Working Length X-ray, etc.
 *
 * Protocols are configured in Settings → Clinical Library → Protocols.
 * They are applied on upload to guide staff on what files are needed.
 */
class DocumentationProtocol extends Model
{
    use SoftDeletes;

    protected $table = 'documentation_protocols';

    protected $fillable = [
        'name',
        'procedure_type',
        'description',
        'apply_to_new_visits',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'apply_to_new_visits' => 'boolean',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * Steps within this protocol, ordered by sort_order.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(DocumentationProtocolStep::class, 'protocol_id')
                    ->orderBy('sort_order');
    }

    /**
     * Required steps only.
     */
    public function requiredSteps(): HasMany
    {
        return $this->steps()->where('is_required', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProcedure($query, string $procedureType)
    {
        return $query->where('procedure_type', 'like', "%{$procedureType}%");
    }
}
