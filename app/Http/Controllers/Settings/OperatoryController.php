<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Operatory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages clinic operatories (chairs/rooms) via Settings → Clinic → Operatories.
 *
 * All operations are scoped to the authenticated user's branch.
 * This controller only handles the settings CRUD — appointment
 * assignment happens in AppointmentController.
 */
class OperatoryController extends Controller
{
    // ── List (used by appointment dropdowns via JSON) ────────────
    public function index(Request $request)
    {
        $branchId    = Auth::user()->branch_id;
        $operatories = Operatory::forBranch($branchId)->ordered()->get();

        if ($request->expectsJson()) {
            return response()->json($operatories);
        }

        // Redirect to settings page on the operatories tab
        return redirect()->route('settings.index', ['tab' => 'operatories']);
    }

    // ── Store ────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'display_order' => 'nullable|integer|min:0|max:999',
            'is_active'     => 'nullable|boolean',
        ]);

        $branchId = Auth::user()->branch_id;

        // Auto-set display_order to end of list if not provided
        if (empty($data['display_order'])) {
            $data['display_order'] = Operatory::forBranch($branchId)->max('display_order') + 1;
        }

        $operatory = Operatory::create([
            'branch_id'     => $branchId,
            'name'          => $data['name'],
            'display_order' => $data['display_order'],
            'is_active'     => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'ok'        => true,
            'operatory' => $operatory,
        ]);
    }

    // ── Update (name, display_order, is_active) ─────────────────
    public function update(Request $request, Operatory $operatory)
    {
        $this->authorizeOperatory($operatory);

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:100',
            'display_order' => 'nullable|integer|min:0|max:999',
            'is_active'     => 'nullable|boolean',
        ]);

        $operatory->update($data);

        return response()->json([
            'ok'        => true,
            'operatory' => $operatory->fresh(),
        ]);
    }

    // ── Toggle active/inactive ───────────────────────────────────
    public function toggle(Operatory $operatory)
    {
        $this->authorizeOperatory($operatory);

        $operatory->update(['is_active' => ! $operatory->is_active]);

        return response()->json([
            'ok'        => true,
            'is_active' => $operatory->is_active,
        ]);
    }

    // ── Reorder (bulk display_order update) ─────────────────────
    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:operatories,id',
        ]);

        $branchId = Auth::user()->branch_id;

        foreach ($request->order as $position => $id) {
            Operatory::where('id', $id)
                     ->where('branch_id', $branchId)
                     ->update(['display_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Destroy ─────────────────────────────────────────────────
    public function destroy(Operatory $operatory)
    {
        $this->authorizeOperatory($operatory);

        // The FK is set to nullOnDelete, so linked appointments just lose their operatory ref
        $operatory->delete();

        return response()->json(['ok' => true]);
    }

    // ── Private ─────────────────────────────────────────────────

    private function authorizeOperatory(Operatory $operatory): void
    {
        abort_if($operatory->branch_id !== Auth::user()->branch_id, 403);
    }
}
