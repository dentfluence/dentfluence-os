<?php

namespace App\Modules\Hq\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hq\Models\Clinic;
use App\Modules\Hq\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::query()
            ->with('clinic')
            ->when($request->status === 'open', fn ($q) => $q->open())
            ->when(in_array($request->status, ['resolved', 'closed']), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(priority, 'urgent','high','normal','low')")
            ->latest()
            ->get();

        return view('hq.tickets.index', [
            'tickets' => $tickets,
            'clinics' => Clinic::orderBy('name')->get(['id', 'name']),
            'status'  => $request->status,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => 'nullable|exists:clinics,id',
            'subject'   => 'required|string|max:255',
            'body'      => 'nullable|string',
            'channel'   => 'required|in:whatsapp,phone,email,in_person',
            'priority'  => 'required|in:low,normal,high,urgent',
        ]);

        $ticket = Ticket::create($data);

        return redirect()->route('hq.tickets.show', $ticket)->with('ok', 'Ticket logged.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('clinic');

        return view('hq.tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status'     => 'required|in:open,in_progress,waiting_on_clinic,resolved,closed',
            'priority'   => 'sometimes|in:low,normal,high,urgent',
            'resolution' => 'nullable|string',
        ]);

        if (in_array($data['status'], ['resolved', 'closed']) && ! $ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return back()->with('ok', 'Ticket updated.');
    }
}
