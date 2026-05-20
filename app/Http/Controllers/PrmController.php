<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrmController extends Controller
{
    /**
     * Pipeline board — main kanban view.
     */
    public function index()
    {
        $stages = $this->getStages();
        $leads  = $this->getDummyLeads();
        $stats  = $this->getPipelineStats($leads);

        return view('communication.prm.index', compact('stages', 'leads', 'stats'));
    }

    /**
     * Kanban board partial (used when switching views via JS/HTMX).
     */
    public function board()
    {
        $stages = $this->getStages();
        $leads  = $this->getDummyLeads();

        return view('communication.prm.board', compact('stages', 'leads'));
    }

    /**
     * Lead detail drawer — loaded via AJAX when clicking a lead card.
     */
    public function leadDetail($id)
    {
        $lead       = $this->findLead($id);
        $activities = $this->getDummyActivities($id);

        return view('communication.prm.lead-detail', compact('lead', 'activities'));
    }

    /**
     * Add lead page — full form.
     */
    public function addLead()
    {
        $treatments = $this->getTreatments();
        $sources    = $this->getSources();
        $staff      = $this->getStaff();
        $stages     = $this->getStages();
        $languages  = $this->getLanguages();
        $timeSlots  = $this->getTimeSlots();

        return view('communication.prm.add-lead', compact(
            'treatments', 'sources', 'staff', 'stages', 'languages', 'timeSlots'
        ));
    }

    /**
     * Store a new lead (wired to real DB in Session 11).
     */
    public function storeLead(Request $request)
    {
        // Validation will be handled via CreateLeadRequest in Session 11.
        // For now redirect back with success flash.
        return redirect()->route('communication.prm.index')
            ->with('success', 'Lead created successfully.');
    }

    /**
     * Move a lead to a different stage (AJAX / drag-drop).
     */
    public function moveStage(Request $request, $id)
    {
        $request->validate([
            'stage' => 'required|string',
        ]);

        // Real DB update wired in Session 11.
        return response()->json([
            'success' => true,
            'message' => 'Lead moved to ' . $request->stage,
        ]);
    }

    /**
     * Change lead status via the status modal.
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
        ]);
    }

    /**
     * PRM Settings page.
     */
    public function settings()
    {
        return view('communication.prm.settings');
    }

    /**
     * Convert lead to patient.
     */
    public function convertToPatient(Request $request, $id)
    {
        // Real conversion logic in Session 11 via ConvertLeadToPatient Action.
        return response()->json([
            'success'    => true,
            'message'    => 'Lead converted to patient.',
            'patient_id' => null, // populated in Session 11
        ]);
    }

    // ─────────────────────────────────────────────
    //  Private helpers — replaced by DB calls in S9
    // ─────────────────────────────────────────────

    private function getStages(): array
    {
        return [
            ['id' => 'new_lead',     'label' => 'New Lead',    'color' => '#534AB7', 'bg' => '#EEEDFE'],
            ['id' => 'contacted',    'label' => 'Contacted',   'color' => '#0F6E56', 'bg' => '#E1F5EE'],
            ['id' => 'appointment',  'label' => 'Appointment', 'color' => '#854F0B', 'bg' => '#FAEEDA'],
            ['id' => 'consultation', 'label' => 'Consultation','color' => '#185FA5', 'bg' => '#E6F1FB'],
            ['id' => 'plan_given',   'label' => 'Plan Given',  'color' => '#993556', 'bg' => '#FBEAF0'],
            ['id' => 'converted',    'label' => 'Converted',   'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        ];
    }

    private function getDummyLeads(): array
    {
        $raw = json_decode(
            file_get_contents(resource_path('stubs/communication/dummy-leads.json')),
            true
        );

        // Group by stage for the board view.
        $grouped = [];
        foreach ($raw as $lead) {
            $grouped[$lead['stage']][] = $lead;
        }

        return $grouped;
    }

    private function findLead(int $id): array
    {
        $all = json_decode(
            file_get_contents(resource_path('stubs/communication/dummy-leads.json')),
            true
        );
        foreach ($all as $lead) {
            if ($lead['id'] === $id) {
                return $lead;
            }
        }
        abort(404);
    }

    private function getDummyActivities(int $leadId): array
    {
        return [
            ['icon' => 'phone',        'icon_bg' => '#E1F5EE', 'icon_color' => '#0F6E56', 'action' => 'Call Done',            'badge' => 'Interested', 'badge_bg' => '#E1F5EE', 'badge_color' => '#0F6E56', 'desc' => 'Spoke to patient. Interested in implant. Will get back.', 'date' => '10 May 2025, 10:30 AM', 'by' => 'Neha'],
            ['icon' => 'calendar',     'icon_bg' => '#FAEEDA', 'icon_color' => '#854F0B', 'action' => 'Follow-up Scheduled',  'badge' => null,           'desc' => 'Next follow-up on 12 May 2025, 11:00 AM',                 'date' => '10 May 2025, 10:30 AM', 'by' => 'Neha'],
            ['icon' => 'whatsapp',     'icon_bg' => '#E1F5EE', 'icon_color' => '#0F6E56', 'action' => 'WhatsApp Message Sent','badge' => null,           'desc' => 'Sent details of implant procedure and cost.',             'date' => '09 May 2025, 04:15 PM', 'by' => 'Rahul'],
            ['icon' => 'phone-missed', 'icon_bg' => '#F1EFE8', 'icon_color' => '#5F5E5A', 'action' => 'Call Attempted',       'badge' => null,           'desc' => 'No response.',                                            'date' => '09 May 2025, 11:20 AM', 'by' => 'Rahul'],
        ];
    }

    private function getPipelineStats(array $grouped): array
    {
        $total = 0;
        foreach ($grouped as $leads) {
            $total += count($leads);
        }
        return [
            'total'       => 128,
            'converted'   => 26,
            'in_pipeline' => 86,
            'lost'        => 16,
            'grouped'     => array_map(fn($leads) => count($leads), $grouped),
        ];
    }

    private function getTreatments(): array
    {
        return ['Dental Implant','Teeth Whitening','Braces / Orthodontics','Root Canal Treatment','Crown & Bridge','Scaling & Polishing','Aligners','Veneers','Dentures','Smile Makeover','Pediatric Dentistry','Gum Treatment','Other'];
    }

    private function getSources(): array
    {
        return ['Call Manager','WhatsApp','Instagram','Facebook','Google','Website','Walk-in','Referral','Camps','Existing Patient Inquiry','Manual Entry'];
    }

    private function getStaff(): array
    {
        return ['Neha (Front Desk)','Anjali Kapoor','Priya Singh','Siddharth Rao','Dr. Mehta'];
    }

    private function getLanguages(): array
    {
        return ['English','Hindi','Marathi','Gujarati','Tamil','Telugu','Kannada','Bengali'];
    }

    private function getTimeSlots(): array
    {
        return ['Morning (9 AM – 1 PM)','Afternoon (1 PM – 5 PM)','Evening (5 PM – 8 PM)','Anytime'];
    }
}
