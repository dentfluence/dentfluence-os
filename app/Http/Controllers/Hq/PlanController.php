<?php

namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount(['subscriptions as live_count' => fn ($q) => $q->live()])
            ->orderBy('kind')->orderBy('monthly_price')
            ->get();

        return view('hq.plans.index', compact('plans'));
    }

    public function toggle(Plan $plan)
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('ok', $plan->name.' is now '.($plan->is_active ? 'active' : 'inactive').'.');
    }
}
