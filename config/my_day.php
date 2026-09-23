<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The order of the working day
    |--------------------------------------------------------------------------
    | THIS IS A CLINIC POLICY, NOT A PRODUCT DECISION. "Calls first, then lab,
    | then tasks" is set here once and is then identical every day for every
    | person — and that consistency is the actual fix for "staff have no clear
    | direction", more than the page itself.
    |
    | Each band is a time of day. Each source is a kind of work. A source
    | listed nowhere simply does not appear on My Day.
    |
    | Sources: confirm_appointments | unsent_lab | calls | tasks | lab_chase
    |          | low_stock
    */
    'bands' => [
        'now' => [
            'label'   => 'Now',
            'hint'    => 'Before the first patient',
            'sources' => ['confirm_appointments', 'unsent_lab'],
        ],
        'morning' => [
            'label'   => 'This morning',
            'hint'    => 'Between patients',
            'sources' => ['calls', 'tasks', 'lab_chase'],
        ],
        'before_close' => [
            'label'   => 'Before close',
            'hint'    => 'End of day',
            'sources' => ['low_stock'],
        ],
    ],

    /*
    | How many rows one source may contribute. A queue that cannot be finished
    | is a queue nobody starts, so a long tail is truncated with a "view all"
    | link into the module that owns it rather than dumped on the page.
    */
    'per_source_limit' => 8,

    /*
    | Roles that land on My Day after login instead of the dashboard. The
    | owner and doctors want the business view; a receptionist wants her list.
    */
    'landing_roles' => ['front_desk', 'assistant', 'accounts'],

];
