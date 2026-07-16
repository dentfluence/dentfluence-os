<?php

namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function index(Request $request)
    {
        $clinics = Clinic::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->open()])
            ->with(['activeSubscriptions.plan'])
            ->orderBy('name')
            ->get();

        return view('hq.clinics.index', compact('clinics'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'city'          => 'nullable|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'status'        => 'required|in:prospect,trial,active,churned',
        ]);

        $clinic = Clinic::create($data);

        return redirect()->route('hq.clinics.show', $clinic)->with('ok', 'Clinic added.');
    }

    public function show(Clinic $clinic)
    {
        $clinic->load(['subscriptions.plan', 'tickets' => fn ($q) => $q->latest()]);

        return view('hq.clinics.show', compact('clinic'));
    }

    public function update(Request $request, Clinic $clinic)
    {
        $data = $request->validate([
            'status'        => 'sometimes|in:prospect,trial,active,churned',
            'notes'         => 'nullable|string',
            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'onboarded_at'  => 'nullable|date',
        ]);

        $clinic->update($data);

        return back()->with('ok', 'Clinic updated.');
    }
}
