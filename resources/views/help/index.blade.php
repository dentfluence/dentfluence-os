@extends('layouts.app')

@section('page-title', 'Help & Support')

@section('content')
<div
    x-data="{
        activeTab: 'income',
        tabs: [
            { id: 'income',   label: 'Income & Expense' },
            { id: 'prm',      label: 'PRM' },
            { id: 'clinical', label: 'Clinical Media' },
            { id: 'marketing',label: 'Marketing' },
            { id: 'reports',  label: 'Reports & Analytics' },
            { id: 'huddle',   label: 'Daily Huddle' },
            { id: 'protocols',label: 'Protocols & Tasks' },
        ]
    }"
    class="p-6 max-w-5xl mx-auto space-y-6"
>

    {{-- ── Page Header ── --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Help & Support</h1>
            <p class="text-sm text-gray-500 mt-1 font-[DM_Sans]">Step-by-step guides for every module in Dentfluence OS</p>
        </div>
        <span class="text-xs text-gray-400 font-[DM_Sans] border border-gray-200 px-3 py-1 rounded-full">v1.0 — {{ now()->format('M Y') }}</span>
    </div>

    {{-- ── Tab Bar ── --}}
    <div class="flex flex-wrap gap-1 border-b border-gray-200 pb-0">
        <template x-for="tab in tabs" :key="tab.id">
            <button
                @click="activeTab = tab.id"
                :class="activeTab === tab.id
                    ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-semibold bg-[#f9f3fa]'
                    : 'text-gray-500 hover:text-[#6a0f70] hover:bg-gray-50'"
                class="px-4 py-2.5 text-xs uppercase tracking-widest font-[DM_Sans] transition -mb-px"
                x-text="tab.label"
            ></button>
        </template>
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 1 — INCOME & EXPENSE
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'income'" x-transition class="space-y-3">

        <div class="bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800 font-[DM_Sans]">
            <strong>What this module does:</strong> Track every rupee that comes in (patient payments, insurance) and goes out (vendor bills, salaries, expenses). Gives you a live picture of clinic cash flow.
        </div>

        @php
        $incomeAccordions = [
            [
                'title' => '1. Recording a Patient Payment',
                'steps' => [
                    'Go to <strong>Finance → Wallet / Payments</strong> from the sidebar.',
                    'Search the patient by name or phone number.',
                    'Click <strong>+ Add Payment</strong> and enter the amount, payment mode (Cash / Card / UPI / Insurance), and reference number if any.',
                    'Click <strong>Save</strong>. The payment is logged to the patient wallet and the daily ledger automatically.',
                    '<em>Tip:</em> You can split a payment across multiple modes — e.g. ₹500 Cash + ₹1,000 UPI.',
                ]
            ],
            [
                'title' => '2. Raising an Invoice',
                'steps' => [
                    'Navigate to the patient\'s profile → <strong>Billing</strong> tab.',
                    'Click <strong>New Invoice</strong>. Select the treatment items from the treatment plan or type them manually.',
                    'Apply discounts or insurance adjustments if needed.',
                    'Click <strong>Generate Invoice</strong>. A PDF invoice is created and can be printed or WhatsApp-shared.',
                    'The invoice is linked to the patient ledger and appears in Finance reports.',
                ]
            ],
            [
                'title' => '3. Recording an Expense',
                'steps' => [
                    'Go to <strong>Finance → Expenses</strong>.',
                    'Click <strong>+ New Expense</strong>.',
                    'Fill in: Category (Rent / Salaries / Supplies / etc.), Amount, Date, Vendor name, and attach a receipt photo if available.',
                    'Click <strong>Save</strong>. This expense is deducted from the daily P&L automatically.',
                    '<em>Tip:</em> Use consistent categories so the Reports page gives clean breakdowns.',
                ]
            ],
            [
                'title' => '4. Checking Daily / Monthly Income vs Expense',
                'steps' => [
                    'Go to <strong>Finance → Dashboard</strong>.',
                    'The top cards show Today\'s Collection, Month-to-Date Revenue, and Outstanding Dues.',
                    'Scroll down to see the Income vs Expense bar chart — select the date range from the filter.',
                    'Click any bar to drill into individual transactions for that day.',
                ]
            ],
            [
                'title' => '5. Managing Vendor Bills (Accounts Payable)',
                'steps' => [
                    'Go to <strong>Finance → Payables</strong> (or Procurement → Vendor Invoices).',
                    'Open a vendor invoice and mark it as <strong>Paid</strong> once payment is made.',
                    'The system records the outflow date and links it to the vendor account.',
                    'You can view ageing reports — bills overdue by 30 / 60 / 90 days.',
                ]
            ],
        ];
        @endphp

        @foreach($incomeAccordions as $i => $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 2 — PRM (Patient Relationship Manager)
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'prm'" x-transition class="space-y-3">

        <div class="bg-purple-50 border border-purple-200 px-4 py-3 text-sm text-purple-800 font-[DM_Sans]">
            <strong>What PRM does:</strong> Manages your entire patient relationship — follow-ups, recalls, lead tracking, communication history, and patient satisfaction. Think of it as your clinic's CRM.
        </div>

        @php
        $prmAccordions = [
            [
                'title' => '1. Adding a New Lead (Enquiry)',
                'steps' => [
                    'Go to <strong>PRM → Leads</strong> from the sidebar.',
                    'Click <strong>+ New Lead</strong>. Enter name, phone, treatment interest, and source (Google / WhatsApp / Walk-in).',
                    'Assign the lead to a staff member for follow-up.',
                    'Set a <strong>Follow-up Date</strong>. The system will show this lead in the Today\'s Follow-ups list on that date.',
                    'As the lead progresses, update the stage: New → Contacted → Appointment Booked → Converted.',
                ]
            ],
            [
                'title' => '2. Setting a Recall for an Existing Patient',
                'steps' => [
                    'Open the patient profile → <strong>PRM</strong> tab.',
                    'Click <strong>+ Set Recall</strong>. Choose a recall type (6-month checkup, cleaning due, etc.) and date.',
                    'The system will automatically surface this patient in the Recalls Due list when the date approaches.',
                    'Staff can then call/WhatsApp the patient and mark the recall as <strong>Resolved</strong> once the appointment is booked.',
                ]
            ],
            [
                'title' => '3. Using the Follow-Up Queue',
                'steps' => [
                    'Go to <strong>PRM → Follow-ups</strong>.',
                    'Today\'s due follow-ups are listed at the top — sorted by priority.',
                    'Click a patient row to see their history and last interaction.',
                    'Log the outcome: Called (No Answer) / Rescheduled / Appointment Booked / Not Interested.',
                    'If rescheduled, set the next follow-up date before closing.',
                ]
            ],
            [
                'title' => '4. Viewing a Patient\'s Full Communication Timeline',
                'steps' => [
                    'Open any patient profile.',
                    'Click the <strong>Timeline</strong> tab — this shows every SMS, WhatsApp, call log, appointment, and invoice in chronological order.',
                    'Use this before calling a patient so you know exactly what was last discussed.',
                ]
            ],
            [
                'title' => '5. Tracking Lost Patients (Dropoff)',
                'steps' => [
                    'Go to <strong>PRM → Inactive Patients</strong>.',
                    'This shows patients who haven\'t visited in 90+ days (configurable in settings).',
                    'Select multiple patients and send a bulk re-engagement message via WhatsApp or SMS.',
                    'Track responses in the Inbox.',
                ]
            ],
        ];
        @endphp

        @foreach($prmAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 3 — CLINICAL MEDIA MANAGER
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'clinical'" x-transition class="space-y-3">

        <div class="bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 font-[DM_Sans]">
            <strong>What Clinical Media Manager does:</strong> Stores X-rays, intraoral photos, consent forms, and clinical notes linked to each patient. Everything is organised by date and tooth number.
        </div>

        @php
        $clinicalAccordions = [
            [
                'title' => '1. Uploading X-rays or Photos for a Patient',
                'steps' => [
                    'Open the patient profile → <strong>Clinical Media</strong> tab.',
                    'Click <strong>+ Upload</strong>. Select the file(s) from your device (JPG, PNG, DICOM supported).',
                    'Tag the upload: choose the <strong>Type</strong> (X-ray / Intraoral Photo / OPG / CBCT / Consent Form) and tooth number if applicable.',
                    'Add a brief note (e.g., "Pre-treatment RVG #16").',
                    'Click <strong>Save</strong>. The file is stored securely and linked to this patient permanently.',
                ]
            ],
            [
                'title' => '2. Comparing Before & After Photos',
                'steps' => [
                    'Go to the patient\'s Clinical Media tab.',
                    'Select two images (hold Ctrl / Cmd to multi-select) and click <strong>Compare</strong>.',
                    'A side-by-side view opens. You can show this to patients on a tablet for case presentations.',
                    'Click <strong>Share</strong> to send the comparison via WhatsApp directly from this screen.',
                ]
            ],
            [
                'title' => '3. Attaching Media to a Consultation Note',
                'steps' => [
                    'When writing a consultation note (Consultations module), scroll to the <strong>Attachments</strong> section.',
                    'Click <strong>Attach from Media Library</strong> — this opens the patient\'s existing media.',
                    'Select the relevant X-ray or photo. It gets embedded in the consultation note for that date.',
                    'Any new uploads here are also saved to the Media Library automatically.',
                ]
            ],
            [
                'title' => '4. Downloading or Sharing Media with the Patient',
                'steps' => [
                    'Open the patient\'s Clinical Media tab and click on any image to open the full view.',
                    'Click <strong>Download</strong> to save it locally, or <strong>WhatsApp Share</strong> to send it directly to the patient\'s registered mobile number.',
                    'All share actions are logged in the patient timeline for audit purposes.',
                ]
            ],
            [
                'title' => '5. Managing Consent Forms',
                'steps' => [
                    'Go to patient profile → Clinical Media → filter by type <strong>Consent Form</strong>.',
                    'Upload a signed physical consent form scan here, OR use <strong>Digital Consent</strong> (if enabled) to send a form via WhatsApp for the patient to sign on their phone.',
                    'Signed digital consents are auto-saved here with a timestamp.',
                ]
            ],
        ];
        @endphp

        @foreach($clinicalAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 4 — MARKETING
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'marketing'" x-transition class="space-y-3">

        <div class="bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 font-[DM_Sans]">
            <strong>What Marketing does:</strong> Send bulk WhatsApp / SMS campaigns, create festive or treatment-specific broadcasts, track delivery & response rates, and manage your brand content library.
        </div>

        @php
        $marketingAccordions = [
            [
                'title' => '1. Sending a Bulk WhatsApp Campaign',
                'steps' => [
                    'Go to <strong>Marketing → Campaigns</strong>.',
                    'Click <strong>+ New Campaign</strong>. Give it a name (e.g., "Diwali Offer 2025").',
                    'Choose the <strong>Audience</strong>: All patients / Inactive patients / Patients with pending treatment / Custom tag.',
                    'Select or create the <strong>Message Template</strong>. Templates must be WhatsApp-approved — use the pre-built ones for reliability.',
                    'Schedule the send time or click <strong>Send Now</strong>.',
                    'Track delivery, open, and reply rates in the campaign report.',
                ]
            ],
            [
                'title' => '2. Creating a Message Template',
                'steps' => [
                    'Go to <strong>Marketing → Templates</strong>.',
                    'Click <strong>+ New Template</strong>. Write the message. Use <code>{{name}}</code> for the patient\'s first name, <code>{{clinic}}</code> for clinic name — these get personalised on send.',
                    'Add an image or PDF attachment if needed (e.g., a treatment offer flyer).',
                    'Save as Draft, then click <strong>Submit for Approval</strong> if it\'s a WhatsApp Business template.',
                    '<em>Note:</em> SMS templates do not need approval and can be sent immediately.',
                ]
            ],
            [
                'title' => '3. Running a Recall Campaign',
                'steps' => [
                    'Go to <strong>Marketing → Recall Campaigns</strong>.',
                    'Set the recall rule: e.g., "Send to patients whose last visit was 6 months ago and have not booked yet."',
                    'Choose the message template and send schedule (e.g., every Monday at 10am).',
                    'The system automatically identifies eligible patients each week and sends without manual effort.',
                ]
            ],
            [
                'title' => '4. Posting to Google Business Profile / Social (CMS)',
                'steps' => [
                    'Go to <strong>Marketing → Content (CMS)</strong>.',
                    'Click <strong>+ New Post</strong>. Write your post, add an image from the media library.',
                    'Choose destination: Google Business Post / Facebook / Instagram (connected accounts).',
                    'Schedule for the optimal time or publish immediately.',
                    'View engagement stats (views, clicks) from the CMS dashboard.',
                ]
            ],
            [
                'title' => '5. Tracking Lead Sources (Attribution)',
                'steps' => [
                    'Go to <strong>Marketing → Lead Sources</strong>.',
                    'This report shows which source brought in the most leads this month: Google / WhatsApp / Referral / Walk-in / Social.',
                    'Use this to decide where to spend your marketing budget.',
                    'Compare lead-to-conversion rate per source to find your highest-quality channel.',
                ]
            ],
        ];
        @endphp

        @foreach($marketingAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 5 — REPORTS & ANALYTICS
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'reports'" x-transition class="space-y-3">

        <div class="bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-800 font-[DM_Sans]">
            <strong>What Reports & Analytics does:</strong> Gives the clinic owner and manager a complete financial, operational, and clinical performance picture — daily, monthly, or custom date range.
        </div>

        @php
        $reportsAccordions = [
            [
                'title' => '1. Daily Collection Report',
                'steps' => [
                    'Go to <strong>Reports → Daily Collection</strong>.',
                    'Select the date (defaults to today).',
                    'See a breakdown: Total Collection / Cash / Card / UPI / Insurance / Outstanding.',
                    'Click <strong>Export PDF</strong> to download the day-end summary for accounts.',
                    '<em>Best practice:</em> Run this every evening before closing to reconcile the cash drawer.',
                ]
            ],
            [
                'title' => '2. Monthly Revenue & P&L Report',
                'steps' => [
                    'Go to <strong>Reports → Monthly P&L</strong>.',
                    'Select month and year.',
                    'The report shows: Gross Revenue, Discounts Given, Net Collection, Total Expenses by category, and Net Profit.',
                    'Click <strong>Drill Down</strong> on any line item to see individual transactions.',
                    'Export to Excel for your chartered accountant.',
                ]
            ],
            [
                'title' => '3. Doctor-wise Production Report',
                'steps' => [
                    'Go to <strong>Reports → Doctor Production</strong>.',
                    'Select date range and doctor name.',
                    'See: Number of patients seen, treatments completed, revenue generated, and average ticket value.',
                    'This is useful for incentive calculations and performance reviews.',
                ]
            ],
            [
                'title' => '4. Treatment Category Analysis',
                'steps' => [
                    'Go to <strong>Reports → Treatment Mix</strong>.',
                    'This shows which treatments are most popular and most profitable: Crowns / RCT / Cleaning / Implants etc.',
                    'Identify your revenue drivers and under-performing services.',
                    'Use this to decide which treatments to promote in your marketing campaigns.',
                ]
            ],
            [
                'title' => '5. Outstanding Dues & Collections',
                'steps' => [
                    'Go to <strong>Reports → Outstanding Dues</strong>.',
                    'Lists all patients with pending balances, sorted by amount owed.',
                    'Click a patient to view their ledger and call them from this screen.',
                    'Mark a follow-up reminder directly from this report.',
                ]
            ],
            [
                'title' => '6. Appointment & No-Show Analysis',
                'steps' => [
                    'Go to <strong>Reports → Appointments</strong>.',
                    'See: Total booked, Completed, Cancelled, No-Show, and Rescheduled — by day, week, or month.',
                    'No-show rate above 15% means you need stronger reminder campaigns (set up in Marketing).',
                    'Drill down by doctor or time slot to find patterns.',
                ]
            ],
        ];
        @endphp

        @foreach($reportsAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════
         TAB 6 — DAILY HUDDLE
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'huddle'" x-transition class="space-y-3">

        <div class="bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 font-[DM_Sans]">
            <strong>What Daily Huddle does:</strong> A 5-minute morning briefing screen for the whole team. Shows today's schedule, pending follow-ups, yesterday's revenue, lab jobs due, and critical alerts — all in one view.
        </div>

        @php
        $huddleAccordions = [
            [
                'title' => '1. Opening the Daily Huddle',
                'steps' => [
                    'Click the <strong>Daily Huddle</strong> button at the top-right of the Dashboard, or go to <strong>Huddle</strong> in the sidebar.',
                    'The huddle screen is designed to be displayed on a shared monitor or large screen during your morning team meeting.',
                    'It loads fresh data automatically — no need to refresh.',
                ]
            ],
            [
                'title' => '2. What Each Section Shows',
                'steps' => [
                    '<strong>Today\'s Schedule:</strong> Full appointment list with patient names, treatment types, and doctor assignments.',
                    '<strong>Yesterday\'s Revenue:</strong> Quick comparison to the same day last week.',
                    '<strong>Lab Jobs Due:</strong> Any crowns, dentures, or orthodontic work expected from the lab today.',
                    '<strong>Pending Follow-ups:</strong> PRM tasks due today — who needs to be called.',
                    '<strong>Recalls Due:</strong> Patients overdue for their recall appointment.',
                    '<strong>Outstanding Bills:</strong> Flagged patients with large unpaid balances checking in today.',
                ]
            ],
            [
                'title' => '3. Using Huddle to Assign Morning Tasks',
                'steps' => [
                    'During the huddle, the front desk manager can assign follow-up calls directly — click the patient row and tap <strong>Assign to Staff</strong>.',
                    'Lab jobs can be confirmed as received or flagged as delayed from this screen.',
                    'Alerts (e.g., "Patient X has an allergy note") are highlighted in red — ensure the treating doctor sees these before the appointment.',
                ]
            ],
            [
                'title' => '4. Evening Wrap-Up Checklist',
                'steps' => [
                    'At end of day, reopen Huddle and switch to <strong>End of Day</strong> view.',
                    'Verify: All appointments marked as Complete / Cancelled / No-show.',
                    'Reconcile cash collection with the Finance daily report.',
                    'Any pending lab dispatches should be logged before closing.',
                    'Mark the huddle as <strong>Closed</strong> — this timestamps the wrap-up for audit.',
                ]
            ],
            [
                'title' => '5. Tips for Running an Effective Huddle',
                'steps' => [
                    'Keep it to 5–7 minutes. The screen is designed to guide the conversation — don\'t read every line, just flag exceptions.',
                    'Focus on: Any VIP patients today? Any complex cases the assistant should prepare for? Any financial alerts?',
                    'Rotate who leads the huddle each week — it builds ownership across the team.',
                    'If a doctor is absent, reassign their appointments from the Appointments module before the huddle, so the schedule is accurate.',
                ]
            ],
        ];
        @endphp

        @foreach($huddleAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach

    </div>

    {{-- ════════════════════════════════════════════════
         TAB 7 — PRACTICE PROTOCOLS & TASKS
    ════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'protocols'" x-transition class="space-y-3">

        <div class="bg-[#f9f3fa] border border-[#e7cdec] px-4 py-3 text-sm text-[#6a0f70] font-[DM_Sans]">
            <strong>What this module does:</strong> Turn your clinic's standard daily/weekly/monthly duties into <strong>Practice Protocols</strong> — defined once per role, each with its own SOP. The system automatically creates a <strong>Task</strong> for the right staff member every time a protocol is due, optionally requiring photo/PDF proof on completion. Use <strong>Tasks</strong> directly for one-off jobs that aren't routine.
        </div>

        <div class="bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 font-[DM_Sans]">
            <strong>Protocol vs Task — the difference:</strong> A <strong>Protocol</strong> is a reusable template tied to a role (e.g. “Assistant — run autoclave test, daily”). It generates Tasks automatically and carries an SOP. A <strong>Task</strong> is a single to-do for one person. Every protocol-generated task is just a normal task with a <em>Protocol</em> badge.
        </div>

        @php
        $protocolAccordions = [
            [
                'title' => 'A. Adding a new Protocol (Admin / Manager)',
                'steps' => [
                    'Open <strong>Operations → Practice Protocols</strong> from the sidebar.',
                    'Click <strong>+ Add Protocol</strong>.',
                    'Enter a clear <strong>Title</strong> (e.g. “End-of-day cash &amp; card reconciliation”) and an optional description.',
                    'Choose the <strong>Role</strong> that performs it — this decides <em>who</em> receives the task. Everyone holding that role gets it.',
                    'Set the <strong>Branch</strong> (leave as “All branches” unless it is branch-specific).',
                    'Pick a <strong>Category</strong> (Clinical / Decon / Reception / Admin / Maintenance…) and a <strong>Priority</strong>.',
                    'Click <strong>Create protocol</strong> — you land on the edit screen where you can add its SOP.',
                ]
            ],
            [
                'title' => 'B. Setting the schedule (frequency)',
                'steps' => [
                    '<strong>Daily</strong> — a task is created every day.',
                    '<strong>Weekly</strong> — choose the <strong>day of week</strong> (e.g. every Monday).',
                    '<strong>Monthly</strong> — choose the <strong>day of month</strong> (1–28; kept ≤28 so short months never skip it).',
                    '<strong>One-off</strong> — generates a single task.',
                    'Set a <strong>Default due time</strong> (e.g. 08:30) so the task shows the expected time.',
                    'Toggle <strong>Active</strong> on to start generating tasks; toggle it off any time to pause without deleting.',
                ]
            ],
            [
                'title' => 'C. Attaching an SOP or materials',
                'steps' => [
                    'On the protocol\'s <strong>edit</strong> screen, scroll to <strong>SOP &amp; Materials</strong>.',
                    'For a step-by-step SOP: choose type <strong>SOP steps</strong>, give it a title, and type <strong>one step per line</strong>. These appear to staff under “View SOP” on the task.',
                    'For a document: choose <strong>File upload</strong> and attach a JPG, PNG, PDF or Word file (max 5 MB).',
                    'For an external guide: choose <strong>Link</strong> and paste the URL.',
                    'Click <strong>Add material</strong>. You can add several materials to one protocol. Remove any with <strong>Remove</strong>.',
                    '<em>Tip:</em> Keep SOP steps short and action-led — staff read them on a phone between patients.',
                ]
            ],
            [
                'title' => 'D. How protocols turn into tasks (generation)',
                'steps' => [
                    'Each night the system automatically creates the day\'s tasks from every <strong>Active</strong> protocol that is due, and assigns one to each staff member holding the matching role.',
                    'To generate immediately (e.g. right after adding protocols), an admin can run <strong>php artisan protocols:generate</strong> in the terminal.',
                    'Generation is <strong>safe to repeat</strong> — it never creates duplicates for the same protocol, person and day.',
                    '<em>Note:</em> if a protocol generates no tasks, no staff currently hold that role — assign the correct role to those team members under <strong>HR → Staff</strong>.',
                ]
            ],
            [
                'title' => 'E. Executing a task (all staff)',
                'steps' => [
                    'Open <strong>Operations → Tasks</strong>. Your protocol tasks carry a purple <strong>Protocol</strong> badge.',
                    'Use the <strong>All / Protocols</strong> toggle at the top to see only protocol tasks.',
                    'Click <strong>View SOP</strong> on a task to read its steps before you start.',
                    'When finished, click <strong>✓ Complete</strong>. The task moves to Completed and disappears from your pending list.',
                    'Use the <strong>My Tasks</strong> tab to focus only on what is assigned to you.',
                ]
            ],
            [
                'title' => 'F. Tasks that require evidence (proof)',
                'steps' => [
                    'Some protocols are marked <strong>Evidence required</strong> (e.g. autoclave test, cash-up). These tasks show an amber <strong>Evidence required</strong> badge.',
                    'You <strong>cannot</strong> mark these complete with the normal button — you must attach proof.',
                    'Click <strong>Attach proof &amp; complete</strong>, then select a clear photo or PDF (JPG / PNG / PDF, max 5 MB).',
                    'The file uploads, the task is completed in one step, and the proof is stored for audit.',
                    '<em>Tip:</em> photograph the printout / reading clearly so it is legible if reviewed later.',
                ]
            ],
            [
                'title' => 'G. Creating a one-off Task (not a protocol)',
                'steps' => [
                    'For a job that isn\'t routine, go to <strong>Operations → Tasks → Assign Task</strong>.',
                    'Enter the task, assign it to a staff member, set the due date/time, priority and category.',
                    'Toggle <strong>Require evidence on completion</strong> if you need proof for that one task.',
                    'Click <strong>Assign Task</strong>. The person is notified and sees it on their board.',
                    'Use protocols for anything that repeats; use one-off tasks for genuinely ad-hoc work.',
                ]
            ],
            [
                'title' => 'H. Tracking compliance (Admin / Manager)',
                'steps' => [
                    'Open <strong>Daily Huddle → Report</strong> and choose a period (Week / Month / Quarter / Year).',
                    'The <strong>Practice Protocol Compliance</strong> section shows totals: tasks generated, completed, missed, and overall completion rate.',
                    'The per-person table shows each staff member\'s Assigned / Done / Missed and a completion bar (green ≥ 80%, amber ≥ 50%, red below).',
                    'Use this in the huddle to recognise consistency and follow up on missed standard duties.',
                ]
            ],
            [
                'title' => 'I. Good practice for managing protocols',
                'steps' => [
                    'Start small — add your 5–10 most important daily routines first, then expand.',
                    'Reserve <strong>Evidence required</strong> for duties that matter for safety, compliance or money (sterilisation, fire checks, cash-up).',
                    'Review protocols quarterly — pause (deactivate) anything no longer relevant rather than deleting history.',
                    'Keep one protocol = one duty. Don\'t bundle several jobs into a single protocol, or the SOP and proof get muddled.',
                    'Make sure each staff member\'s <strong>role</strong> is set correctly in HR — that is what routes the right protocols to the right people.',
                ]
            ],
        ];
        @endphp

        @foreach($protocolAccordions as $item)
        <div x-data="{ open: false }" class="border border-gray-200 bg-white">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <span class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ $item['title'] }}</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-100">
                <ol class="px-6 py-4 space-y-2 list-decimal list-outside text-sm text-gray-700 font-[DM_Sans]">
                    @foreach($item['steps'] as $step)
                    <li class="ml-4">{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
        @endforeach

    </div>

    {{-- ── Footer ── --}}
    <div class="border-t border-gray-100 pt-4 text-xs text-gray-400 font-[DM_Sans] text-center">
        Dentfluence OS Help Guide &nbsp;·&nbsp; For support contact your system administrator
    </div>

</div>
@endsection
