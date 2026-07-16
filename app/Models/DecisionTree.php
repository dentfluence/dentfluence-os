<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DecisionTree — reusable template owned by Dentfluence (frozen §5.3).
 * Clinics never fork it; doctors curate instances per patient.
 */
class DecisionTree extends Model
{
    protected $fillable = [
        'slug', 'title', 'entry_condition', 'version', 'status',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(DecisionTreeNode::class)->orderBy('sort_order');
    }

    /** Root nodes only (no parent). */
    public function rootNodes(): HasMany
    {
        return $this->hasMany(DecisionTreeNode::class)
            ->whereNull('parent_node_id')
            ->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
