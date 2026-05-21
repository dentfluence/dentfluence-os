<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrmController extends Controller
{
    public function index()
    {
        $leads = $this->getDummyLeads();

        $stages = [
            'new_lead'      => ['label' => 'New Lead',     'color' => '#185FA5'],
            'contacted'     => ['label' => 'Contacted',    'color' => '#0F6E56'],
            'appointment'   => ['label' => 'Appointment',  'color' => '#854F0B'],
            'consultation'  => ['label' => 'Consultation', 'color' => '#534AB7'],
            'plan_given'    => ['label' => 'Plan Given',   'color' => '#3B6D11'],
            'converted'     => ['label' => 'Converted',    'color' => '#0F6E56'],
            'lost'          => ['label' => 'Lost',         'color' => '#5F5E5A'],
        ];

        $grouped = collect($leads)->groupBy('stage');

        $stats = [
            'total'       => count($leads),
            'converted'   => $grouped->get('converted', collect())->count(),
            'in_pipeline' => collect($leads)->whereNotIn('stage', ['converted', 'lost'])->count(),
            'lost'        => $grouped->get('lost', collect())->count(),
        ];

        $navCounts  = $this->getNavCounts();
        $sources    = $this->getSources();       // ← add these three
        $treatments = $this->getTreatments();   // ← 
        $staff      = $this->getStaff();        // ←

        return view('communication.prm.index', compact(
            'leads',
            'stages',
            'grouped',
            'stats',
            'navCounts',
            'sources',
            'treatments',
            'staff'               // ← add to compact
        ));
    }
    public function addLead()
    {
        $navCounts = $this->getNavCounts();
        $sources = $this->getSources();
        $treatments = $this->getTreatments();
        $staff = $this->getStaff();
        return view('communication.prm.add-lead', compact('navCounts', 'sources', 'treatments', 'staff'));
    }

    public function storeLead(Request $request)
    {
        // Session 11 will wire this to DB
        return redirect()->route('prm.index')->with('success', 'Lead added successfully.');
    }

    public function leadDetail($id)
    {
        $leads = $this->getDummyLeads();
        $lead  = collect($leads)->firstWhere('id', (int)$id) ?? $leads[0];
        $navCounts = $this->getNavCounts();
        return view('communication.prm.lead-detail', compact('lead', 'navCounts'));
    }

    public function editLead($id)
    {
        $leads = $this->getDummyLeads();
        $lead  = collect($leads)->firstWhere('id', (int)$id) ?? $leads[0];
        $navCounts  = $this->getNavCounts();
        $sources    = $this->getSources();
        $treatments = $this->getTreatments();
        $staff      = $this->getStaff();
        return view('communication.prm.add-lead', compact('lead', 'navCounts', 'sources', 'treatments', 'staff'));
    }

    public function updateLead(Request $request, $id)
    {
        return redirect()->route('prm.lead-detail', $id)->with('success', 'Lead updated.');
    }

    // ─── Dummy data (replaced in Session 11) ───────────────────────────

    private function getDummyLeads(): array
    {
        $raw = json_decode(file_get_contents(resource_path('stubs/communication/dummy-leads.json')), true);
        return $raw ?? [];
    }

    private function getNavCounts(): array
    {
        return [
            'overdue'   => 18,
            'today'     => 34,
            'long_term' => 23,
            'ongoing'   => 16,
            'yesterday' => 12,
            'special'   => 7,
        ];
    }

    private function getSources(): array
    {
        return ['WhatsApp', 'Instagram', 'Facebook', 'Google', 'Website', 'Walk-in', 'Camp', 'Referral', 'Call Manager', 'Manual'];
    }

    private function getTreatments(): array
    {
        return ['Dental Implant', 'Teeth Whitening', 'Braces / Aligners', 'Root Canal', 'Scaling & SRP', 'Extraction', 'Crown & Bridge', 'Dentures', 'Smile Makeover', 'Paediatric Dentistry', 'Other'];
    }

    private function getStaff(): array
    {
        return [
            ['id' => 1, 'name' => 'Neha (Front Desk)'],
            ['id' => 2, 'name' => 'Anjali Kapoor (Coordinator)'],
            ['id' => 3, 'name' => 'Priya Singh (Front Desk)'],
            ['id' => 4, 'name' => 'Dr. Mehta (Dentist)'],
        ];
    }
}
