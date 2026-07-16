<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DecisionTreeNode — a node in a decision tree (frozen §5.3).
 * POINTERS ONLY — never prices, never prose. Education via kb_topic_id; priced
 * choices via treatment_option_group. `conditions` reserved (equality matcher).
 */
class DecisionTreeNode extends Model
{
    protected $fillable = [
        'decision_tree_id', 'parent_node_id', 'node_type', 'kb_topic_id',
        'treatment_id', 'treatment_option_group', 'conditions', 'label',
        'sort_order', 'is_terminal',
    ];

    protected $casts = [
        'conditions'  => 'array',
        'is_terminal' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function tree(): BelongsTo
    {
        return $this->belongsTo(DecisionTree::class, 'decision_tree_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_node_id')->orderBy('sort_order');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(KbTopic::class, 'kb_topic_id');
    }

    /** The Treatment whose priced options (treatment_option_group) apply here. */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }
}
