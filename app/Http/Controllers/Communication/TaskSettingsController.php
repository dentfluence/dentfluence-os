<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\ActionOptionList;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tasks > Settings — the outcome vocabulary, per task category.
 *
 * Deliberately NOT part of the PRE settings page. The Tasks module owns its
 * own settings for the same reason it owns its own list: "PRE engine che task
 * vegle, task manager che vegle" (CEO, 6 Sep). Mixing them would also put task
 * categories into the PRE board's category list.
 *
 * Rows are never deleted — only deactivated — because task_outcomes rows
 * already in the history reference these keys.
 */
class TaskSettingsController extends Controller
{
    public function index()
    {
        $groups = ActionOptionList::query()
            ->where('option_type', 'task_outcome')
            ->orderBy('action_category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('action_category');

        // Maintenance types are NOT per task category — one clinic-wide list of
        // the things that get serviced. Same table, own option_type.
        $maintenanceTypes = ActionOptionList::query()
            ->where('option_type', 'maintenance_type')
            ->orderBy('sort_order')
            ->get();

        return view('tasks.settings', [
            'groups'           => $groups,
            'categories'       => Task::CATEGORIES,
            'maintenanceTypes' => $maintenanceTypes,
        ]);
    }

    public function save(Request $request, ActionOptionList $option): RedirectResponse
    {
        abort_unless($option->option_type === 'task_outcome', 404);

        $data = $request->validate([
            'label'      => ['required', 'string', 'max:150'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $option->update([
            'label'          => $data['label'],
            'sort_order'     => $data['sort_order'],
            'closes_task'    => $request->boolean('closes_task'),
            'requires_notes' => $request->boolean('requires_notes'),
            'is_active'      => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Saved \"{$option->label}\".");
    }

    /**
     * Update one maintenance type (AC service, pest control, …).
     *
     * Separate from save() because these carry no closes_task / requires_notes
     * meaning — folding them into one method would mean silently writing
     * fields that mean nothing here.
     */
    public function saveMaintenanceType(Request $request, ActionOptionList $option): RedirectResponse
    {
        abort_unless($option->option_type === 'maintenance_type', 404);

        $data = $request->validate([
            'label'      => ['required', 'string', 'max:150'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $option->update([
            'label'      => $data['label'],
            'sort_order' => $data['sort_order'],
            'is_active'  => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Saved \"{$option->label}\".");
    }

    /** Add a maintenance type. */
    public function addMaintenanceType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        $key = Str::slug($data['label'], '_');

        $exists = ActionOptionList::query()
            ->where('option_type', 'maintenance_type')
            ->where('key', $key)
            ->exists();

        if ($exists) {
            return back()->with('error', 'A maintenance type with a matching key already exists — use a slightly different label.');
        }

        $nextOrder = (int) ActionOptionList::query()
            ->where('option_type', 'maintenance_type')
            ->max('sort_order') + 1;

        ActionOptionList::create([
            'option_type'     => 'maintenance_type',
            'action_category' => null,
            'key'             => $key,
            'label'           => $data['label'],
            'sort_order'      => $nextOrder,
            'closes_task'     => false,
            'requires_notes'  => false,
            'is_active'       => true,
        ]);

        return back()->with('success', "Added \"{$data['label']}\".");
    }

    public function add(Request $request, string $category): RedirectResponse
    {
        abort_unless(array_key_exists($category, Task::CATEGORIES), 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        $key = Str::slug($data['label'], '_');

        $exists = ActionOptionList::query()
            ->where('option_type', 'task_outcome')
            ->where('action_category', $category)
            ->where('key', $key)
            ->exists();

        if ($exists) {
            return back()->with('error', 'An outcome with a matching key already exists here — use a slightly different label.');
        }

        $nextOrder = (int) ActionOptionList::query()
            ->where('option_type', 'task_outcome')
            ->where('action_category', $category)
            ->max('sort_order') + 1;

        ActionOptionList::create([
            'option_type'     => 'task_outcome',
            'action_category' => $category,
            'key'             => $key,
            'label'           => $data['label'],
            'sort_order'      => $nextOrder,
            // Default to closing. The common case is a resolved outcome; the
            // admin unticks "Closes the task" for a retry-style one.
            'closes_task'     => true,
            'requires_notes'  => false,
            'is_active'       => true,
        ]);

        return back()->with('success', "Added \"{$data['label']}\".");
    }
}
