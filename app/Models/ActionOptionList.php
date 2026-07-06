<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ActionOptionList — clinic-editable dropdown options for the Today's
 * Actions call workflow (call outcomes + dismiss reasons).
 *
 * See docs/feature-specs/feature-spec-custom-call-outcomes.md and
 * docs/feature-specs/feature-spec-action-board-dismiss.md.
 *
 * Two option_type values share this table:
 *   'call_outcome'   — scoped per action_category, falls back to
 *                       config('relationship_rules.response_options') if a
 *                       category has no active rows yet.
 *   'dismiss_reason' — shared across all categories, action_category is null.
 */
class ActionOptionList extends Model
{
    protected $fillable = [
        'option_type',
        'action_category',
        'key',
        'label',
        'closes_task',
        'requires_notes',
        'next_action_key',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'closes_task'    => 'boolean',
        'requires_notes' => 'boolean',
        'sort_order'     => 'integer',
        'is_active'      => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Active call-outcome options for one action category, ordered for display.
     * Returns an empty collection if none are configured — the caller
     * (TodayController::index) is responsible for falling back to
     * config('relationship_rules.response_options') in that case.
     */
    public function scopeCallOutcomesFor($query, string $category)
    {
        return $query->where('option_type', 'call_outcome')
            ->where('action_category', $category)
            ->active();
    }

    /**
     * Active dismiss-reason options — shared across every category.
     */
    public function scopeDismissReasons($query)
    {
        return $query->where('option_type', 'dismiss_reason')
            ->active();
    }

    /** key => label map, in display order — the shape the Blade/Alpine view already expects. */
    public static function labelMap(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->key] = $row->label;
        }

        return $map;
    }
}
