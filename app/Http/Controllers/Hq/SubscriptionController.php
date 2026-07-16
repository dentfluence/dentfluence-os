<?php

namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::query()
            ->with(['clinic', 'plan'])
            ->when($request->filter === 'live',     fn ($q) => $q->live())
            ->when($request->filter === 'expiring', fn ($q) => $q->expiringWithin(30))
            ->when($request->filter === 'lapsed',   fn ($q) => $q->lapsed())
            ->when($request->filter === 'cancelled',fn ($q) => $q->where('status', 'cancelled'))
            ->orderBy('expires_at')
            ->get();

        return view('hq.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'clinics'       => Clinic::orderBy('name')->get(['id', 'name']),
            'plans'         => Plan::where('is_active', true)->orderBy('kind')->get(),
            'filter'        => $request->filter,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id'     => 'required|exists:clinics,id',
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'amount'        => 'required|integer|min:0',
            'starts_at'     => 'required|date',
            'expires_at'    => 'required|date|after:starts_at',
            'notes'         => 'nullable|string',
        ]);

        Subscription::create($data);

        return back()->with('ok', 'Subscription added.');
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled']);

        return back()->with('ok', 'Subscription cancelled.');
    }

    // Renew = new row, same clinic/plan, so history stays intact.
    public function renew(Request $request, Subscription $subscription)
    {
        $months = $subscription->billing_cycle === 'annual' ? 12 : 1;
        $start  = $subscription->expires_at->isFuture() ? $subscription->expires_at : now();

        Subscription::create([
            'clinic_id'     => $subscription->clinic_id,
            'plan_id'       => $subscription->plan_id,
            'billing_cycle' => $subscription->billing_cycle,
            'amount'        => $request->integer('amount') ?: $subscription->amount,
            'starts_at'     => $start,
            'expires_at'    => $start->copy()->addMonths($months),
            'status'        => 'active',
        ]);

        return back()->with('ok', 'Renewed.');
    }
}
