<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    /**
     * Patient list — search & select a patient/lead to view timeline
     */
    public function index(Request $request)
    {
        $patients = $this->getDummyPatients();

        if ($request->filled('q')) {
            $q = strtolower($request->q);
            $patients = array_filter($patients, function ($p) use ($q) {
                return str_contains(strtolower($p['name']), $q)
                    || str_contains($p['phone'], $q);
            });
        }

        return view('communication.timeline.index', [
            'patients'    => array_values($patients),
            'searchQuery' => $request->q ?? '',
        ]);
    }

    /**
     * Full communication timeline for a specific patient/lead
     * Alias used in communication.php route file.
     */
    public function patient(Request $request, $personId)
    {
        return $this->show($request, $personId);
    }

    /**
     * Full communication timeline for a specific patient/lead
     */
    public function show(Request $request, $personId)
    {
        $person   = $this->getDummyPersonById($personId);
        $timeline = $this->getDummyTimeline($personId);

        $filterType = $request->get('type', 'all');
        if ($filterType !== 'all') {
            $timeline = array_filter($timeline, fn($e) => $e['type'] === $filterType);
            $timeline = array_values($timeline);
        }

        return view('communication.timeline.patient-timeline', [
            'person'     => $person,
            'timeline'   => $timeline,
            'filterType' => $filterType,
            'personId'   => $personId,
        ]);
    }

    // ─────────────────────────────────────────────
    // DUMMY DATA (replace with DB queries in Session 11)
    // ─────────────────────────────────────────────

    private function getDummyPatients(): array
    {
        return [
            ['id' => 1,  'name' => 'Riya Sharma',    'phone' => '98765 43210', 'type' => 'lead',    'status' => 'New Lead',          'treatment' => 'Dental Implant',  'avatar' => 'RS', 'last_activity' => '2 hours ago',  'assigned_to' => 'Neha'],
            ['id' => 2,  'name' => 'Amit Kulkarni',  'phone' => '98201 23456', 'type' => 'lead',    'status' => 'Contacted',         'treatment' => 'Braces',          'avatar' => 'AK', 'last_activity' => '4 hours ago',  'assigned_to' => 'Neha'],
            ['id' => 3,  'name' => 'Priya Singh',    'phone' => '97654 32109', 'type' => 'lead',    'status' => 'Appointment Fixed', 'treatment' => 'Root Canal',      'avatar' => 'PS', 'last_activity' => 'Yesterday',    'assigned_to' => 'Rahul'],
            ['id' => 4,  'name' => 'Neha Kapoor',    'phone' => '98987 65432', 'type' => 'patient', 'status' => 'Active Patient',    'treatment' => 'Aligners',        'avatar' => 'NK', 'last_activity' => '3 days ago',   'assigned_to' => 'Neha'],
            ['id' => 5,  'name' => 'Vikram Mehta',   'phone' => '99876 12345', 'type' => 'patient', 'status' => 'Ongoing Treatment', 'treatment' => 'Scaling & SRP',   'avatar' => 'VM', 'last_activity' => '1 week ago',   'assigned_to' => 'Anjali'],
            ['id' => 6,  'name' => 'Sunita Joshi',   'phone' => '98450 67890', 'type' => 'lead',    'status' => 'Contacted',         'treatment' => 'Teeth Whitening', 'avatar' => 'SJ', 'last_activity' => '5 hours ago',  'assigned_to' => 'Rahul'],
            ['id' => 7,  'name' => 'Karan Malhotra', 'phone' => '98123 45678', 'type' => 'patient', 'status' => 'Active Patient',    'treatment' => 'Crown & Bridge',  'avatar' => 'KM', 'last_activity' => '2 days ago',   'assigned_to' => 'Anjali'],
            ['id' => 8,  'name' => 'Pooja Desai',    'phone' => '98234 56789', 'type' => 'patient', 'status' => 'Completed',         'treatment' => 'Veneer',          'avatar' => 'PD', 'last_activity' => '2 weeks ago',  'assigned_to' => 'Neha'],
            ['id' => 9,  'name' => 'Rohit Tiwari',   'phone' => '98712 34567', 'type' => 'lead',    'status' => 'New Lead',          'treatment' => 'Consultation',    'avatar' => 'RT', 'last_activity' => '6 hours ago',  'assigned_to' => 'Rahul'],
            ['id' => 10, 'name' => 'Anjali Verma',   'phone' => '98711 22334', 'type' => 'patient', 'status' => 'Recall Due',        'treatment' => 'Cleaning',        'avatar' => 'AV', 'last_activity' => '1 month ago',  'assigned_to' => 'Neha'],
        ];
    }

    private function getDummyPersonById(int $id): array
    {
        $map = collect($this->getDummyPatients())->keyBy('id')->toArray();
        return $map[$id] ?? [
            'id' => $id,
            'name' => 'Unknown',
            'phone' => '--',
            'type' => 'lead',
            'status' => 'Unknown',
            'treatment' => '--',
            'avatar' => '?',
            'last_activity' => '--',
            'assigned_to' => '--',
        ];
    }

    private function getDummyTimeline(int $personId): array
    {
        return [
            [
                'id' => 1,
                'type' => 'call',
                'subtype' => 'outgoing',
                'title' => 'Outgoing Call',
                'outcome' => 'Interested',
                'description' => 'Spoke to patient. Interested in Dental Implant procedure. Asked about cost and duration. Will visit clinic next week.',
                'date' => '24 May 2025',
                'time' => '10:15 AM',
                'actor' => 'Neha',
                'duration' => '04:32 mins',
                'icon' => 'call',
                'color' => 'green',
            ],
            [
                'id' => 2,
                'type' => 'followup',
                'subtype' => 'scheduled',
                'title' => 'Follow-up Scheduled',
                'outcome' => null,
                'description' => 'Next follow-up scheduled on 26 May 2025, 11:00 AM via Call.',
                'date' => '24 May 2025',
                'time' => '10:16 AM',
                'actor' => 'Neha',
                'duration' => null,
                'icon' => 'calendar',
                'color' => 'purple',
            ],
            [
                'id' => 3,
                'type' => 'note',
                'subtype' => 'general',
                'title' => 'Note Added',
                'outcome' => null,
                'description' => 'Patient interested in teeth whitening as well. Budget concern for implant — suggested EMI options. Follow up with cost breakdown on WhatsApp.',
                'date' => '23 May 2025',
                'time' => '04:30 PM',
                'actor' => 'Anjali',
                'duration' => null,
                'icon' => 'note',
                'color' => 'amber',
            ],
            [
                'id' => 4,
                'type' => 'whatsapp',
                'subtype' => 'sent',
                'title' => 'WhatsApp Message Sent',
                'outcome' => null,
                'description' => 'Sent Dental Implant procedure details, cost breakdown, and before/after photos.',
                'date' => '23 May 2025',
                'time' => '11:00 AM',
                'actor' => 'Anjali',
                'duration' => null,
                'icon' => 'whatsapp',
                'color' => 'teal',
            ],
            [
                'id' => 5,
                'type' => 'status',
                'subtype' => 'change',
                'title' => 'Status Changed',
                'outcome' => 'Contacted → Interested',
                'description' => 'Lead status updated from "Contacted" to "Interested" after call.',
                'date' => '23 May 2025',
                'time' => '10:50 AM',
                'actor' => 'Priya Singh',
                'duration' => null,
                'icon' => 'status',
                'color' => 'blue',
            ],
            [
                'id' => 6,
                'type' => 'call',
                'subtype' => 'outgoing',
                'title' => 'Outgoing Call',
                'outcome' => 'Not Reachable',
                'description' => 'Called to follow up. Number not reachable. Will try again tomorrow.',
                'date' => '22 May 2025',
                'time' => '03:00 PM',
                'actor' => 'Rahul',
                'duration' => '--',
                'icon' => 'call',
                'color' => 'red',
            ],
            [
                'id' => 7,
                'type' => 'followup',
                'subtype' => 'rescheduled',
                'title' => 'Follow-up Rescheduled',
                'outcome' => null,
                'description' => 'Rescheduled from 22 May 03:00 PM to 24 May 10:00 AM. Reason: Patient unavailable.',
                'date' => '22 May 2025',
                'time' => '03:05 PM',
                'actor' => 'Rahul',
                'duration' => null,
                'icon' => 'calendar',
                'color' => 'orange',
            ],
            [
                'id' => 8,
                'type' => 'opportunity',
                'subtype' => 'created',
                'title' => 'Opportunity Created',
                'outcome' => 'Teeth Whitening',
                'description' => 'Patient expressed interest in teeth whitening after Implant completion. Opportunity tagged for post-treatment follow-up.',
                'date' => '21 May 2025',
                'time' => '05:20 PM',
                'actor' => 'Neha',
                'duration' => null,
                'icon' => 'opportunity',
                'color' => 'indigo',
            ],
            [
                'id' => 9,
                'type' => 'call',
                'subtype' => 'incoming',
                'title' => 'Incoming Call',
                'outcome' => 'Callback Requested',
                'description' => 'Patient called. Asked about appointment availability. Requested callback in the afternoon.',
                'date' => '20 May 2025',
                'time' => '09:45 AM',
                'actor' => 'Neha',
                'duration' => '01:05',
                'icon' => 'call',
                'color' => 'blue',
            ],
            [
                'id' => 10,
                'type' => 'note',
                'subtype' => 'call-note',
                'title' => 'Call Note',
                'outcome' => null,
                'description' => 'Patient asked about EMI options for Dental Implant. Shared payment plan details over call.',
                'date' => '19 May 2025',
                'time' => '02:10 PM',
                'actor' => 'Neha',
                'duration' => null,
                'icon' => 'note',
                'color' => 'amber',
            ],
            [
                'id' => 11,
                'type' => 'task',
                'subtype' => 'completed',
                'title' => 'Task Completed',
                'outcome' => 'Done',
                'description' => 'Task: Send implant cost details on WhatsApp — completed.',
                'date' => '18 May 2025',
                'time' => '11:30 AM',
                'actor' => 'Anjali',
                'duration' => null,
                'icon' => 'task',
                'color' => 'green',
            ],
            [
                'id' => 12,
                'type' => 'appointment',
                'subtype' => 'booked',
                'title' => 'Appointment Booked',
                'outcome' => 'Consultation',
                'description' => 'Consultation appointment booked for 26 May 2025 at 11:00 AM with Dr. Mehta.',
                'date' => '17 May 2025',
                'time' => '04:00 PM',
                'actor' => 'Neha',
                'duration' => null,
                'icon' => 'appointment',
                'color' => 'teal',
            ],
            [
                'id' => 13,
                'type' => 'call',
                'subtype' => 'outgoing',
                'title' => 'Outgoing Call',
                'outcome' => 'Connected',
                'description' => 'Initial contact call. Introduced clinic, explained services. Patient showed interest in implants. Scheduled callback.',
                'date' => '15 May 2025',
                'time' => '10:30 AM',
                'actor' => 'Neha',
                'duration' => '03:20 mins',
                'icon' => 'call',
                'color' => 'green',
            ],
            [
                'id' => 14,
                'type' => 'lead',
                'subtype' => 'created',
                'title' => 'Lead Created',
                'outcome' => 'New Lead',
                'description' => 'Lead added from Call Manager. Source: Incoming Call. Assigned to Neha (Front Desk).',
                'date' => '15 May 2025',
                'time' => '10:28 AM',
                'actor' => 'System',
                'duration' => null,
                'icon' => 'lead',
                'color' => 'purple',
            ],
        ];
    }
}
