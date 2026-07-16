<?php

/*
|==========================================================================
| Dentfluence OS — Screen Guide Content
|==========================================================================
| The ONLY place guide text lives. Keys are route names ('patients.create')
| or route-name prefixes ('patients'). Exact match wins over prefix.
|
| Entry shape:
|   'title' => Short screen name shown in the hint strip and panel.
|   'hint'  => ['staff' => one line + concrete example (12th-pass simple),
|               'admin' => one line framed around value/decisions]
|   'what'  => 1-2 sentences: what this screen is for.
|   'tasks' => [[ 'Task name', 'How to do it, with an example.' ], ...]
|   'flows' => Plain sentences: where this data goes / what it triggers.
|   'roi'   => Shown to Admin only: why this screen makes/saves money.
|
| Writing rules: no jargon, one concrete example per hint, guide the
| WORKFLOW not the buttons, keep every string one or two sentences.
|==========================================================================
*/

return [

    /* ── Dashboard ──────────────────────────────────────────────────── */
    'dashboard' => [
        'title' => 'Dashboard',
        'hint'  => [
            'staff' => 'Your day starts here — today\'s appointments and pending work. Example: press Ctrl+K any time to find a patient by name or phone.',
            'admin' => 'Snapshot of today\'s clinic. For deeper numbers, use Reports; for today\'s action list, use the Daily Huddle.',
        ],
        'what'  => 'The landing screen: today\'s appointments, quick numbers and shortcuts to daily work.',
        'tasks' => [
            ['Find any patient fast', 'Press Ctrl+K or click the search bar on top — type a name, phone number or patient ID.'],
            ['Add something new quickly', 'The purple + button (top right) creates a patient, appointment, prescription or payment from any screen.'],
        ],
        'flows' => [
            'Everything shown here is pulled live from Appointments, Billing and Tasks — nothing is entered on this screen itself.',
        ],
        'roi'   => 'A staff member who masters Ctrl+K and the + button saves minutes on every single patient interaction.',
    ],

    /* ── Patients ───────────────────────────────────────────────────── */
    'patients' => [
        'title' => 'Patients',
        'hint'  => [
            'staff' => 'Every patient record lives here. Example: for a walk-in, click the purple + button → New Patient — name and phone are enough to start.',
            'admin' => 'Your patient master list. Recalls, reminders and reports all pull from these records — record quality here decides how well everything else works.',
        ],
        'what'  => 'The master list of all patients. One patient = one record, and every appointment, treatment, bill and document hangs off it.',
        'tasks' => [
            ['Register a new patient', 'Click + (top right) → New Patient. Only name and phone are required — fill the rest when there is time. Example: a walk-in can be registered in under 30 seconds.'],
            ['Find a patient', 'Press Ctrl+K and type any part of their name, phone or patient ID. Example: typing the last 4 digits of a phone number works.'],
            ['See a patient\'s full story', 'Click their name to open the profile — treatments, bills, documents and chats are all tabs on one page. No need to look anywhere else.'],
        ],
        'flows' => [
            'A saved patient can immediately get an appointment, consultation, treatment plan and bill — nothing is typed twice.',
            'The phone number saved here is what WhatsApp confirmations, reminders and recalls are sent to — a wrong number means the patient hears nothing.',
        ],
        'roi'   => 'Complete records make recalls automatic — missed follow-ups come back without anyone having to remember them. Missing or wrong phone numbers are the #1 reason reminders silently fail.',
    ],

    'patients.create' => [
        'title' => 'New Patient',
        'hint'  => [
            'staff' => 'Only name and phone are compulsory — save first, complete details later. Example: for a patient in pain, save name + phone and send them straight in.',
            'admin' => 'The form is deliberately minimal so front desk never queues patients over paperwork. Medical alerts entered here show as a red flag on every future screen.',
        ],
        'what'  => 'Registers a new patient. Designed to be fast: the minimum is a name and a phone number.',
        'tasks' => [
            ['Register in a hurry', 'Type name and phone, press Save. Everything else can be added later from the patient\'s profile.'],
            ['Record a medical alert', 'If the patient mentions diabetes, BP, allergies or blood thinners, enter it in Medical Alert — it will show in red on all their future visits.'],
        ],
        'flows' => [
            'After saving you land on the patient\'s profile, where you can book their appointment or start a consultation right away.',
        ],
        'roi'   => 'A 30-second registration keeps the front desk queue moving; the medical alert field is your first safety net against clinical mistakes.',
    ],

    /* ── Appointments ───────────────────────────────────────────────── */
    'appointments' => [
        'title' => 'Appointments',
        'hint'  => [
            'staff' => 'The clinic\'s schedule. Example: to book Mrs. Sharma for tomorrow 11 am — New Appointment → pick her name → pick doctor and time. She gets a WhatsApp confirmation automatically.',
            'admin' => 'The schedule is your revenue calendar — empty slots and no-shows both show up here first.',
        ],
        'what'  => 'Book, move and track appointments. The day\'s list is what the whole clinic works from.',
        'tasks' => [
            ['Book an appointment', 'Click New Appointment, search the patient (or create them on the spot), choose doctor, date and time. Example: a recall patient calling in can be booked during the same phone call.'],
            ['Reschedule or cancel', 'Open the appointment and change the time or cancel it — the patient is informed on WhatsApp automatically.'],
            ['Mark the visit flow', 'When the patient walks in, mark them Arrived; after the doctor finishes, mark Completed. This keeps the waiting list honest.'],
        ],
        'flows' => [
            'Booking sends a WhatsApp confirmation, and a reminder goes out before the visit (if the patient has given consent).',
            'Completed appointments feed the Daily Huddle and the recall engine — that is how the system knows who to call back and when.',
        ],
        'roi'   => 'Automatic reminders are the cheapest no-show reduction there is — every empty chair-hour is revenue that cannot be recovered later.',
    ],

    'appointments.create' => [
        'title' => 'New Appointment',
        'hint'  => [
            'staff' => 'Search the patient first — if they are new, create them right here without leaving this page. Double-booking a slot will warn you before saving.',
            'admin' => 'The overlap warning protects the schedule; it can be overridden deliberately, but never silently.',
        ],
        'what'  => 'Books a single appointment: patient, doctor, date and time.',
        'tasks' => [
            ['Book for an existing patient', 'Type their name or phone in the patient box and pick them from the list.'],
            ['Book for a brand-new patient', 'If no match is found, add them right here — name and phone are enough.'],
        ],
        'flows' => [
            'On save, the patient receives a WhatsApp confirmation and the slot appears in the day\'s schedule instantly.',
        ],
        'roi'   => 'One-call booking (patient created and booked in the same minute) is what makes phone enquiries convert into visits.',
    ],

    /* ── Billing / Payments ─────────────────────────────────────────── */
    'billing' => [
        'title' => 'Billing',
        'hint'  => [
            'staff' => 'Record every payment the moment it happens. Example: patient pays Rs. 2,000 against a Rs. 5,000 plan — record it here and their balance updates automatically.',
            'admin' => 'Every rupee recorded here flows into the day\'s collection and the patient ledger — end-of-day tallies are only as true as this screen.',
        ],
        'what'  => 'Invoices, payments and receipts. Part-payments and advances are supported — the balance always follows the patient.',
        'tasks' => [
            ['Record a payment', 'From the patient\'s profile open Billing, or use + → New Payment from anywhere. Enter amount and mode (cash / UPI / card) and save.'],
            ['Take a part-payment or EMI', 'Enter whatever the patient pays today — the remaining balance stays on their record and shows on their next visit. Nothing to remember.'],
            ['Print or send a receipt', 'Every payment has a receipt — print it or send it on WhatsApp from the same screen.'],
        ],
        'flows' => [
            'Payments land in the day\'s collection report and the patient\'s ledger the moment they are saved.',
            'If a patient pays extra, it stays in their wallet as an advance and adjusts against their next bill automatically.',
        ],
        'roi'   => 'Same-moment entry is what makes the evening cash tally take five minutes instead of an hour — and what makes your revenue reports trustworthy enough to decide with.',
    ],

    'wallets' => [
        'title' => 'Patient Ledger',
        'hint'  => [
            'staff' => 'This patient\'s money story — every bill, payment and balance in one place. Example: to answer "how much is pending?", the figure at the top is always current.',
            'admin' => 'The single source of truth for one patient\'s account — advances, dues and refunds all reconcile here.',
        ],
        'what'  => 'One patient\'s complete financial record: invoices, payments, advances and outstanding balance.',
        'tasks' => [
            ['Answer a balance query', 'The outstanding figure at the top is live — quote it with confidence.'],
            ['Record a payment', 'Use the payment button here; it links to the right invoice automatically.'],
        ],
        'flows' => [
            'Everything here also appears in the clinic-wide collection and outstanding reports.',
        ],
        'roi'   => 'Instant, confident answers to "what do I owe?" is a patient-trust moment — and clean ledgers are what make outstanding-dues follow-up possible at all.',
    ],

    /* ── Consultations ──────────────────────────────────────────────── */
    'consultations' => [
        'title' => 'Consultation',
        'hint'  => [
            'staff' => 'The doctor\'s clinical record of a visit. Example: a returning patient with the same problem uses "Same Issue" — half the form is already filled.',
            'admin' => 'Consultations are the clinical backbone — treatment plans, prescriptions and recalls all start from what is recorded here.',
        ],
        'what'  => 'Records what the doctor found and decided in a visit. Four flavours: New problem, Same Issue (follow-up), Minor Visit and Emergency — pick the lightest one that fits.',
        'tasks' => [
            ['Start a consultation', 'From the patient\'s profile, choose the visit type. Example: a suture removal is a Minor Visit — 30 seconds, not a full form.'],
            ['Chart teeth', 'Click teeth on the chart to mark findings; there is an adult/child toggle for pediatric patients.'],
            ['Move to treatment', 'From a finished consultation you can create the treatment plan directly — findings carry over.'],
        ],
        'flows' => [
            'Findings flow into the treatment plan; the diagnosis stays on the patient\'s record for every future visit.',
            'A completed consultation marks the visit as clinically logged in the Daily Huddle.',
        ],
        'roi'   => 'Good notes protect you medico-legally and make every future visit faster — the doctor never starts from a blank page twice.',
    ],

    /* ── Treatment Plans ────────────────────────────────────────────── */
    'treatment-plans' => [
        'title' => 'Treatment Plan',
        'hint'  => [
            'staff' => 'The costed plan the patient says yes (or no) to. Example: print or WhatsApp the plan so the patient can discuss it at home — acceptance can be recorded later.',
            'admin' => 'This is where revenue is won or lost — a written, itemised plan with clear pricing is the single biggest lever on case acceptance.',
        ],
        'what'  => 'An itemised list of proposed treatments with prices. The patient accepts all or part of it; accepted items drive visits and billing.',
        'tasks' => [
            ['Build a plan', 'Add procedures (with teeth) and prices. You can build it straight from consultation findings so nothing is missed.'],
            ['Present it', 'Print or share the plan — it is written for the patient, not just the file.'],
            ['Record acceptance', 'When the patient agrees, mark Accept (full or partial). Consent forms print from the same place.'],
        ],
        'flows' => [
            'Accepted items become the work list for treatment visits and the basis of invoices — billing never has to guess what was agreed.',
            'Unaccepted plans are not lost: they become follow-up opportunities in the relationship engine.',
        ],
        'roi'   => 'Clinics that present written plans convert more high-value cases; unaccepted plans followed up systematically are the cheapest revenue you will ever recover.',
    ],

    /* ── Prescriptions ──────────────────────────────────────────────── */
    'prescriptions' => [
        'title' => 'Prescriptions',
        'hint'  => [
            'staff' => 'Write and print prescriptions here. Example: use + → New Prescription from any screen — pick the patient, pick the drugs, print.',
            'admin' => 'Every prescription is stored on the patient record — a complete drug history at a glance, and a professional printed slip every time.',
        ],
        'what'  => 'Creates printed/shareable prescriptions from the clinic\'s drug list, saved permanently on the patient\'s record.',
        'tasks' => [
            ['Write a prescription', 'Pick drugs from the list — dose and instructions fill in with sensible defaults you can adjust.'],
            ['Reprint an old one', 'Open the patient\'s Prescriptions tab — every past prescription can be viewed and reprinted.'],
        ],
        'flows' => [
            'Prescriptions live on the patient profile alongside their treatments — the full clinical story stays in one place.',
        ],
        'roi'   => 'A printed prescription with the clinic\'s letterhead is a small daily branding moment — and the stored history answers "what did we give last time?" instantly.',
    ],

    /* ── Daily Huddle ───────────────────────────────────────────────── */
    'huddle' => [
        'title' => 'Daily Huddle',
        'hint'  => [
            'staff' => 'The morning meeting screen — today\'s plan and yesterday\'s loose ends in one place. Example: start each day by clearing the Yesterday\'s Flow list.',
            'admin' => 'Ten minutes here each morning replaces an hour of "what happened yesterday?" — it is the clinic\'s daily operating rhythm.',
        ],
        'what'  => 'One screen for the morning team meeting: today\'s appointments, yesterday\'s unfinished work, pending tasks and the numbers that matter today.',
        'tasks' => [
            ['Run the morning huddle', 'Open this screen with the team, walk today\'s list top to bottom, and assign anything unowned.'],
            ['Close yesterday\'s loose ends', 'Yesterday\'s Flow shows visits without a logged consultation or payment — chase each one before it goes stale.'],
            ['Check period reports', 'Weekly / Monthly tabs show the same picture over longer periods.'],
        ],
        'flows' => [
            'The huddle reads from everywhere — appointments, billing, tasks, lab — it is a mirror, nothing is entered here.',
        ],
        'roi'   => 'Clinics that huddle daily catch missed payments and unlogged visits within 24 hours instead of at month-end, when they are unrecoverable.',
    ],

    /* ── Tasks ──────────────────────────────────────────────────────── */
    'tasks' => [
        'title' => 'Tasks',
        'hint'  => [
            'staff' => 'Your to-do list with owners and due dates. Example: "call the lab about case #42" becomes a task assigned to you — nothing lives on sticky notes.',
            'admin' => 'Assigned tasks with due dates are how instructions survive a busy day — verbal instructions do not.',
        ],
        'what'  => 'Clinic to-dos: who does what, by when. Tasks appear in the Daily Huddle until done.',
        'tasks' => [
            ['Create a task', 'Name it, assign a person, set a due date. Example: assign "confirm tomorrow\'s first three appointments" to the front desk every evening.'],
            ['Work your list', 'Mark tasks done as you finish — overdue ones stay visible until handled.'],
        ],
        'flows' => [
            'Open and overdue tasks surface in the Daily Huddle automatically.',
        ],
        'roi'   => 'Every forgotten instruction is either lost revenue or a patient annoyed — a task list with owners costs nothing and forgets nothing.',
    ],

    /* ── PRE / Relationships ────────────────────────────────────────── */
    'relationship' => [
        'title' => 'Patient Relationships',
        'hint'  => [
            'staff' => 'Who needs a call today — recalls, follow-ups and replies, in one action list. Example: work the Action Board top to bottom and log each call\'s outcome.',
            'admin' => 'This is the revenue-recovery engine: it finds patients who should come back and turns them into an action list — no one has to remember anyone.',
        ],
        'what'  => 'The relationship engine: automatic recalls, follow-ups on pending treatments, WhatsApp conversations and a daily Action Board of who to contact.',
        'tasks' => [
            ['Work the Action Board', 'Each row is one patient to contact, with the reason. Call, then log the outcome — the row closes or reschedules itself.'],
            ['Reply on WhatsApp', 'Incoming patient messages appear here — reply from the patient\'s Communication tab without picking up a phone.'],
            ['Check a patient\'s history', 'The relationship profile shows every message and call ever exchanged with that patient.'],
        ],
        'flows' => [
            'Completed treatments automatically schedule future recalls; missed appointments and unaccepted plans automatically appear as follow-up actions.',
            'Every message respects the patient\'s consent settings — the system will not message someone who opted out.',
        ],
        'roi'   => 'Recall and follow-up patients are the highest-margin visits a clinic gets — they cost nothing to acquire. This board is where that money is collected.',
    ],

    /* ── Inventory ──────────────────────────────────────────────────── */
    'inventory' => [
        'title' => 'Inventory',
        'hint'  => [
            'staff' => 'Clinic stock in and out. Example: when a box of gloves is opened for use, issue it here — the count stays honest.',
            'admin' => 'Stock counts drive reorder alerts and consumption costs — accurate issues are what make the numbers trustworthy.',
        ],
        'what'  => 'Tracks materials and consumables: what is in stock, what is running low, what was used.',
        'tasks' => [
            ['Issue stock for use', 'When material is taken for treatment, record the issue — takes seconds at the cupboard.'],
            ['Receive a purchase', 'When an order arrives, receive it against the purchase order — stock and vendor dues update together.'],
            ['Act on low-stock alerts', 'The dashboard flags items below their reorder level before you run out mid-procedure.'],
        ],
        'flows' => [
            'Received purchases create vendor dues in Finance automatically; issues feed consumption reports.',
        ],
        'roi'   => 'Running out of an implant component mid-case costs a rebooked patient; over-ordering ties up cash. Both problems are solved by honest counts.',
    ],

    /* ── Lab ────────────────────────────────────────────────────────── */
    'lab' => [
        'title' => 'Lab Cases',
        'hint'  => [
            'staff' => 'Every crown, denture and appliance sent to a lab, tracked till it is fitted. Example: check "due back today" each morning before confirming fit appointments.',
            'admin' => 'Lab delays are the most common reason for rescheduled appointments — this screen makes every case\'s status visible before the patient is in the chair.',
        ],
        'what'  => 'Tracks lab work end to end: prescription to the lab, dispatch, expected return, receipt and fit.',
        'tasks' => [
            ['Send a case', 'Create the case with the lab prescription (work type, shade, teeth) and mark it dispatched.'],
            ['Track what is due', 'The list shows every case by status — chase anything overdue before the patient\'s fit visit.'],
            ['Receive and fit', 'Mark the case received when it arrives; link it to the fit appointment.'],
        ],
        'flows' => [
            'Lab charges flow into Finance as expenses against the case; the case status is visible from the patient\'s profile too.',
        ],
        'roi'   => 'One avoided "sorry, your crown hasn\'t arrived" call per week pays for the discipline — rescheduled fits are lost chair time and lost goodwill.',
    ],

    /* ── Finance ────────────────────────────────────────────────────── */
    'finance' => [
        'title' => 'Finance',
        'hint'  => [
            'staff' => 'Money beyond the front desk: expenses, vendor bills and the day\'s totals. Example: photograph and enter an expense bill the day it happens, not at month-end.',
            'admin' => 'Collections, expenses, payables and P&L in one place — this is where you see whether the month is actually working.',
        ],
        'what'  => 'The clinic\'s money centre: collections, expenses, vendor dues (payables), patient dues and profit-and-loss views.',
        'tasks' => [
            ['Record an expense', 'Enter category, amount and vendor the day it happens — attach the bill photo.'],
            ['Settle vendor bills', 'Open payables, mark bills paid as you pay them — ageing (30/60/90 days) keeps you honest.'],
            ['Read the dashboard', 'Today\'s collection, month-to-date and outstanding dues sit on top; drill into any figure for the transactions behind it.'],
        ],
        'flows' => [
            'Patient payments arrive here from Billing automatically; inventory purchases arrive as payables — you only enter what happens outside the system.',
        ],
        'roi'   => 'Same-day expense entry is the difference between a P&L you trust and one you argue with — and outstanding-dues visibility is found money.',
    ],

    /* ── Marketing ──────────────────────────────────────────────────── */
    'marketing' => [
        'title' => 'Marketing',
        'hint'  => [
            'staff' => 'Campaigns, content and the clinic\'s public face. Example: plan next month\'s posts in the calendar in one sitting.',
            'admin' => 'Marketing here is connected to real outcomes — campaigns link to actual enquiries and bookings, not just likes.',
        ],
        'what'  => 'Plans and tracks the clinic\'s marketing: content calendar, campaigns and brand assets.',
        'tasks' => [
            ['Plan content', 'Use the calendar to schedule posts and campaigns ahead of time instead of scrambling daily.'],
            ['Keep brand assets in one place', 'Logos, templates and approved photos live in the brand kit — no more hunting through phones.'],
        ],
        'flows' => [
            'Enquiries generated by campaigns flow into the relationship engine as leads to be converted.',
        ],
        'roi'   => 'The cheapest new patient is a referral or a returning one — but when you do spend on marketing, this is how you know what the spend returned.',
    ],

    /* ── Clinical Library ───────────────────────────────────────────── */
    'cms' => [
        'title' => 'Clinical Library',
        'hint'  => [
            'staff' => 'The clinic\'s photo and X-ray library. Example: capture before/after photos into the patient\'s folder — they file themselves.',
            'admin' => 'Organised clinical media is consent-ready marketing material and medico-legal protection in one.',
        ],
        'what'  => 'Stores clinical photos, X-rays and videos, organised by patient and treatment.',
        'tasks' => [
            ['Capture to the right place', 'Always shoot from within the patient\'s record so media files itself — never to the phone gallery.'],
            ['Find media fast', 'Filter by patient, date or treatment type when the doctor asks for "that fracture case from March".'],
        ],
        'flows' => [
            'Media attaches to the patient record permanently; consented before/afters can feed marketing.',
        ],
        'roi'   => 'Before/after photos are the most persuasive case-acceptance tool a dentist has — but only if they can be found when the patient is in the chair.',
    ],

    /* ── Reviews ────────────────────────────────────────────────────── */
    'reviews' => [
        'title' => 'Reviews',
        'hint'  => [
            'staff' => 'Ask happy patients for a Google review at the right moment. Example: send the review request while the patient is still smiling at the desk.',
            'admin' => 'Review volume and recency drive local search ranking — a steady trickle beats an occasional burst.',
        ],
        'what'  => 'Sends review requests to patients and tracks the clinic\'s public reputation.',
        'tasks' => [
            ['Request a review', 'After a good visit, trigger the request — the patient gets a direct link, no searching.'],
        ],
        'flows' => [
            'Requests respect patient consent; responses build the clinic\'s public profile where new patients search.',
        ],
        'roi'   => 'For most clinics, Google reviews are the highest-converting marketing asset that exists — and they are free.',
    ],

    /*
    |──────────────────────────────────────────────────────────────────
    | Core workflows — cross-module stories shown on the /help page.
    | Never matched against routes (underscore prefix). Each step is
    | [Screen, What you do there].
    |──────────────────────────────────────────────────────────────────
    */
    '_workflows' => [
        [
            'title'  => 'Walk-in to payment — the daily spine',
            'goal'   => 'A new patient walks in with a problem and leaves treated, billed and recorded — with nothing typed twice.',
            'steps'  => [
                ['Patients',        'Register with just name + phone (+ button → New Patient). Under 30 seconds.'],
                ['Consultation',    'Doctor records findings and diagnosis; charts the teeth.'],
                ['Treatment Plan',  'Itemised plan with prices, built from the findings. Patient accepts fully or partially.'],
                ['Appointments',    'Book the treatment visits for the accepted work.'],
                ['Billing',         'Record the payment — full, part or advance. Receipt prints or goes on WhatsApp.'],
            ],
            'payoff' => 'One continuous chain: the plan knows the findings, the bill knows the plan, the ledger knows the bill. No paper, no re-typing, no "what did we quote?"',
        ],
        [
            'title'  => 'Phone call to filled chair — schedule discipline',
            'goal'   => 'A phone enquiry becomes a confirmed, reminded, arrived patient.',
            'steps'  => [
                ['Appointments', 'Book during the call itself — create the patient right inside the booking form if they are new.'],
                ['(automatic)',  'WhatsApp confirmation goes out at booking; reminder goes out before the visit.'],
                ['Appointments', 'Mark Arrived when they walk in; Completed when the doctor finishes.'],
            ],
            'payoff' => 'Reminders cut no-shows without anyone making reminder calls; the Arrived/Completed marks keep the waiting room and the Huddle honest.',
        ],
        [
            'title'  => 'Treatment done to patient returned — the recall cycle',
            'goal'   => 'Patients come back when they should, without anyone maintaining a diary.',
            'steps'  => [
                ['(automatic)',            'Completing treatment schedules the future recall by itself.'],
                ['Patient Relationships',  'When the recall falls due, the patient appears on the Action Board with the reason.'],
                ['Patient Relationships',  'Call or WhatsApp from the board; log the outcome — booked, later, or not interested.'],
                ['Appointments',           'Book the visit — the cycle starts again.'],
            ],
            'payoff' => 'Recall patients cost nothing to acquire and accept treatment at the highest rate. This cycle is the single most profitable habit in the system.',
        ],
        [
            'title'  => 'Crown sent to crown fitted — the lab loop',
            'goal'   => 'Lab work arrives before the patient does, every time.',
            'steps'  => [
                ['Lab Cases',    'Create the case with the lab prescription and mark it dispatched.'],
                ['Lab Cases',    'Watch the due-back list each morning; chase anything late before confirming the fit visit.'],
                ['Lab Cases',    'Mark received when it arrives; fit at the appointment.'],
                ['Finance',      'The lab charge lands as an expense against the case automatically.'],
            ],
            'payoff' => 'No more fitting appointments cancelled at the chair because the crown is still at the lab.',
        ],
        [
            'title'  => 'Morning huddle to evening tally — the money day',
            'goal'   => 'Every rupee of the day is seen twice: planned in the morning, counted in the evening.',
            'steps'  => [
                ['Daily Huddle', 'Morning: walk today\'s list and yesterday\'s loose ends with the team.'],
                ['Billing',      'Through the day: record every payment the moment it happens.'],
                ['Finance',      'Through the day: enter expenses when they occur, with the bill photo.'],
                ['Finance',      'Evening: today\'s collection on the dashboard should match the cash box and UPI screen — in five minutes, not an hour.'],
            ],
            'payoff' => 'Clinics that close their day daily never face month-end mysteries — and the reports become trustworthy enough to make decisions with.',
        ],
        [
            'title'  => 'Low stock to paid vendor — the supply loop',
            'goal'   => 'Materials never run out mid-procedure, and vendor dues never surprise you.',
            'steps'  => [
                ['Inventory', 'Low-stock alert flags the item before it runs out.'],
                ['Inventory', 'Raise the purchase order; receive the delivery against it when it arrives.'],
                ['Finance',   'The vendor bill appears in payables automatically — mark it paid when you pay.'],
            ],
            'payoff' => 'Stock-outs cost rebooked patients; surprise vendor dues cost cash-flow panic. Both disappear with this loop.',
        ],
    ],

];
