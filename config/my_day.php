<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The order of the working day
    |--------------------------------------------------------------------------
    | THIS IS A CLINIC POLICY, NOT A PRODUCT DECISION. The sequence below is
    | set here once and is then identical every day for every person — and that
    | consistency is the actual fix for "staff have no clear direction", more
    | than the page itself.
    |
    | ── BY KIND OF WORK, NOT BY TIME (CEO ruling, 23 Sep) ─────────────────────
    | V1 grouped the day into NOW / THIS MORNING / BEFORE CLOSE. Sumit's
    | verdict: "my day madhye timewise nako". Two reasons it was wrong:
    |
    |   1. The clinic does not run to that clock. A receptionist between
    |      patients does whatever the next gap allows, not what a heading says
    |      belongs to 11am.
    |   2. It split one kind of work across the page. Lab cases to send sat in
    |      NOW and lab cases to chase sat in THIS MORNING, so "what is the lab
    |      situation" meant scrolling past the calls to find the other half.
    |
    | The order is his, in his words: calls first, then tasks, then lab, then
    | money that is overdue. Each section is ONE kind of work, whole.
    |
    | Sources: confirm_appointments | unsent_lab | calls | tasks | lab_chase
    |          | overdue_expenses
    | Boards:  calls | tasks
    |
    | ── STOCK IS OFF (CEO ruling, 23 Sep) ─────────────────────────────────────
    | A 'stock' section ran last, below the four. It is removed: reordering
    | stock is a buying decision made against a supplier and a price, not
    | something a receptionist does between patients, and it was the one
    | section on the page nobody was going to act on the same day.
    |
    | The QUERY IS NOT DELETED — MyDayQueue::lowStock() still exists and still
    | works. Re-enabling it is adding the band back here, nothing more. And
    | because no band names 'low_stock' any more, that query no longer runs at
    | all: sources are built on demand (see MyDayQueue::collect()).
    |
    | Low stock has not gone anywhere. It is still on the Daily Huddle and in
    | Inventory, which is where a reorder decision actually gets made.
    */
    'bands' => [

        // 1 ── CALLS. Confirming today's diary is a call job, so it leads the
        //      section rather than sitting in a band of its own.
        'calls' => [
            'label'   => 'Calls',
            'hint'    => 'Patients to ring today',
            'sources' => ['confirm_appointments'],
            'boards'  => ['calls'],
        ],

        // 2 ── TASKS.
        'tasks' => [
            'label'   => 'Tasks',
            'hint'    => 'Assigned to you',
            'sources' => [],
            'boards'  => ['tasks'],
        ],

        // 3 ── LAB, whole. Send first, then chase: a case you have not sent
        //      cannot be chased, and seeing both halves together is what makes
        //      "where is the lab work" answerable in one glance.
        'lab' => [
            'label'   => 'Lab',
            'hint'    => 'Send first, then chase',
            'sources' => ['unsent_lab', 'lab_chase'],
        ],

        // 4 ── MONEY GOING OUT, but only when it is late. An unpaid bill that
        //      is not yet due is not today's work; putting it here would teach
        //      people to skim the section.
        'payments' => [
            'label'   => 'Payments due',
            'hint'    => 'Bills past their due date',
            'sources' => ['overdue_expenses'],
        ],
    ],

    /*
    | How many rows one source may contribute. A queue that cannot be finished
    | is a queue nobody starts, so a long tail is truncated rather than dumped
    | on the page.
    */
    'per_source_limit' => 8,

    /*
    | Roles that land on My Day after login instead of the dashboard. The
    | owner and doctors want the business view; a receptionist wants her list.
    */
    'landing_roles' => ['front_desk', 'assistant', 'accounts'],

];
