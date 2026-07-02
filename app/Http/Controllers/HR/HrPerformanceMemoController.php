<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrPerformanceMemo;
use App\Models\User;
use Illuminate\Http\Request;

class HrPerformanceMemoController extends Controller
{
    public function index(Request $request)
    {
        $query = HrPerformanceMemo::with(['staff', 'issuedBy'])
            ->orderByDesc('memo_date');

        if ($request->filled('staff_id')) {
            $query->where('staff_user_id', $request->staff_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $memos = $query->paginate(25)->withQueryString();
        $staff = User::where('is_active', true)->orderBy('name')->get();

        return view('hr.memos.index', compact('memos', 'staff'));
    }

    public function create()
    {
        $staff = User::where('is_active', true)->orderBy('name')->get();
        return view('hr.memos.create', compact('staff'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_user_id'  => 'required|exists:users,id',
            'type'           => 'required|in:praise,warning,improvement,review,general',
            'subject'        => 'required|string|max:255',
            'body'           => 'required|string',
            'memo_date'      => 'required|date',
            'is_confidential'=> 'nullable|boolean',
        ]);

        $data['issued_by']      = auth()->id();
        $data['is_confidential'] = $request->boolean('is_confidential');

        HrPerformanceMemo::create($data);

        return redirect()->route('hr.memos.index')
            ->with('success', 'Memo issued successfully.');
    }

    public function show(HrPerformanceMemo $memo)
    {
        $memo->load(['staff', 'issuedBy']);
        return view('hr.memos.show', compact('memo'));
    }

    public function destroy(HrPerformanceMemo $memo)
    {
        $memo->delete();
        return redirect()->route('hr.memos.index')
            ->with('success', 'Memo deleted.');
    }

    // Staff acknowledges they have read the memo
    public function acknowledge(HrPerformanceMemo $memo)
    {
        if (!$memo->staff_acknowledged) {
            $memo->update([
                'staff_acknowledged' => true,
                'acknowledged_at'    => now(),
            ]);
        }

        return back()->with('success', 'Memo acknowledged.');
    }
}
