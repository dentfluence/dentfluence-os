<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    /**
     * Main opportunity board / list view.
     */
    public function index(Request $request)
    {
        return view('communication.opportunities.index');
    }
    /**
     * Show detail of a single opportunity.
     */
    public function show(int $id)
    {
        return view('communication.opportunities.detail', compact('id'));
    }

    /**
     * Store a new opportunity (will be fully wired in Session 11).
     */
    public function store(Request $request)
    {
        // Placeholder — validation and DB logic in Session 11
        return back()->with('success', 'Opportunity saved.');
    }

    /**
     * Update stage (from drag-drop or form).
     */
    public function updateStage(Request $request, int $id)
    {
        $request->validate(['stage' => 'required|string']);
        // Placeholder
        return response()->json(['success' => true]);
    }

    /**
     * Convert opportunity to PRM lead.
     */
    public function convertToLead(Request $request, int $id)
    {
        // Placeholder — real logic in Session 10/11
        return response()->json(['success' => true, 'message' => 'Converted to lead.']);
    }

    public function board()
    {
        return view('communication.opportunities.board');
    }
    public function detail(int $id)
    {
        return $this->show($id);
    }
}
