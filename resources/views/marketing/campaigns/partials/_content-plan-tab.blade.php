{{--
|==========================================================================
| Campaign Show — Content Plan Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_content-plan-tab.blade.php
|
| Full Kanban board with 6 columns.
| Mock data only — no database calls.
|==========================================================================
--}}

@php
/*──────────────────────────────────────────────────────────────────────────
   MOCK DATA
──────────────────────────────────────────────────────────────────────────*/
$kanban = [
    'idea' => [
        'label' => 'Idea',
        'color' => '#7b68ee',
        'bg'    => '#f0eeff',
        'count' => 6,
        'cards' => [
            ['platform'=>'instagram','type'=>'Reel',     'title'=>'Before & After Smile Transformation',         'date'=>'Jul 5',  'assignee'=>'SR'],
            ['platform'=>'facebook', 'type'=>'Post',     'title'=>'5 Signs You Need a Dental Check-up',          'date'=>'Jul 8',  'assignee'=>'PM'],
            ['platform'=>'blog',     'type'=>'Article',  'title'=>'Invisalign vs Braces — Honest Comparison',    'date'=>'Jul 10', 'assignee'=>'NK'],
            ['platform'=>'whatsapp', 'type'=>'Message',  'title'=>'Post-treatment care tips broadcast',          'date'=>'Jul 12', 'assignee'=>'SR'],
        ],
        'overflow' => 2,
        'overflow_label' => 'ideas',
    ],
    'in-progress' => [
        'label' => 'In Progress',
        'color' => '#e67e22',
        'bg'    => '#fff5eb',
        'count' => 4,
        'cards' => [
            ['platform'=>'instagram','type'=>'Carousel', 'title'=>'10 Foods That Stain Your Teeth',              'date'=>'Jun 30', 'assignee'=>'NK'],
            ['platform'=>'blog',     'type'=>'Article',  'title'=>'What to Expect on Your First Visit',         'date'=>'Jul 2',  'assignee'=>'PM'],
            ['platform'=>'facebook', 'type'=>'Video',    'title'=>'Clinic Tour — Behind the Scenes',            'date'=>'Jul 3',  'assignee'=>'SR'],
            ['platform'=>'website',  'type'=>'Banner',   'title'=>'Monsoon Season Oral Health Offer',           'date'=>'Jul 4',  'assignee'=>'NK'],
        ],
        'overflow' => 0,
        'overflow_label' => '',
    ],
    'in-review' => [
        'label' => 'In Review',
        'color' => '#2980b9',
        'bg'    => '#eaf4fc',
        'count' => 3,
        'cards' => [
            ['platform'=>'instagram','type'=>'Story',    'title'=>'Patient Testimonial — Priya K.',             'date'=>'Jun 28', 'assignee'=>'SR'],
            ['platform'=>'google',   'type'=>'Ad',       'title'=>'Google Search Ad — Teeth Whitening',         'date'=>'Jun 29', 'assignee'=>'PM'],
            ['platform'=>'facebook', 'type'=>'Post',     'title'=>'World Oral Health Day Recap',                'date'=>'Jun 29', 'assignee'=>'NK'],
        ],
        'overflow' => 0,
        'overflow_label' => '',
    ],
    'approved' => [
        'label' => 'Approved',
        'color' => '#27ae60',
        'bg'    => '#eafaf1',
        'count' => 5,
        'cards' => [
            ['platform'=>'instagram','type'=>'Reel',     'title'=>'Dentist Q&A — Common Myths Busted',          'date'=>'Jun 25', 'assignee'=>'NK'],
            ['platform'=>'blog',     'type'=>'Article',  'title'=>'Top 7 Benefits of Regular Scaling',         'date'=>'Jun 24', 'assignee'=>'PM'],
            ['platform'=>'facebook', 'type'=>'Carousel', 'title'=>'Meet the Team — Staff Spotlight',           'date'=>'Jun 23', 'assignee'=>'SR'],
            ['platform'=>'website',  'type'=>'Banner',   'title'=>'Summer Smile Package Promo',                'date'=>'Jun 22', 'assignee'=>'NK'],
        ],
        'overflow' => 1,
        'overflow_label' => 'items',
    ],
    'scheduled' => [
        'label' => 'Scheduled',
        'color' => '#8e44ad',
        'bg'    => '#f5eeff',
        'count' => 6,
        'cards' => [
            ['platform'=>'instagram','type'=>'Post',     'title'=>'Monday Motivation — Smile Goals',            'date'=>'Jul 1',  'assignee'=>'SR'],
            ['platform'=>'facebook', 'type'=>'Post',     'title'=>'Did You Know? Enamel Edition',               'date'=>'Jul 1',  'assignee'=>'PM'],
            ['platform'=>'whatsapp', 'type'=>'Message',  'title'=>'Festival Offer Reminder Blast',              'date'=>'Jul 2',  'assignee'=>'NK'],
            ['platform'=>'blog',     'type'=>'Article',  'title'=>'Root Canal Myths — Debunked',               'date'=>'Jul 3',  'assignee'=>'SR'],
        ],
        'overflow' => 2,
        'overflow_label' => 'items',
    ],
    'published' => [
        'label' => 'Published',
        'color' => '#16a085',
        'bg'    => '#e8f8f5',
        'count' => 12,
        'cards' => [
            ['platform'=>'instagram','type'=>'Reel',     'title'=>'Kid-Friendly Dentist Visit Tips',            'date'=>'Jun 10', 'assignee'=>'NK'],
            ['platform'=>'blog',     'type'=>'Article',  'title'=>'Fluoride Treatment — Is It Safe?',          'date'=>'Jun 8',  'assignee'=>'PM'],
            ['platform'=>'facebook', 'type'=>'Video',    'title'=>'Patient Journey — Smile Makeover',          'date'=>'Jun 5',  'assignee'=>'SR'],
            ['platform'=>'google',   'type'=>'Ad',       'title'=>'Google Display — Implants Campaign',        'date'=>'Jun 3',  'assignee'=>'NK'],
        ],
        'overflow' => 8,
        'overflow_label' => 'published',
    ],
];

/* Platform config */
$platforms = [
    'instagram' => ['bg' => 'linear-gradient(135deg,#f09433 0%,#dc2743 50%,#bc1888 100%)', 'label' => 'IG'],
    'facebook'  => ['bg' => '#1877F2', 'label' => 'f'],
    'google'    => ['bg' => '#4285F4', 'label' => 'G'],
    'blog'      => ['bg' => '#21759b', 'label' => 'B'],
    'website'   => ['bg' => '#34495e', 'label' => 'W'],
    'whatsapp'  => ['bg' => '#25d366', 'label' => 'WA'],
];

/* Content type badge colors */
$typeBadge = [
    'Reel'     => ['bg'=>'#fce4ec','text'=>'#b71c1c'],
    'Post'     => ['bg'=>'#e3f2fd','text'=>'#0d47a1'],
    'Story'    => ['bg'=>'#f3e5f5','text'=>'#6a1b9a'],
    'Carousel' => ['bg'=>'#fff3e0','text'=>'#e65100'],
    'Article'  => ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
    'Video'    => ['bg'=>'#fce4ec','text'=>'#880e4f'],
    'Banner'   => ['bg'=>'#e0f2f1','text'=>'#004d40'],
    'Ad'       => ['bg'=>'#ede7f6','text'=>'#311b92'],
    'Message'  => ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
];

/* Assignee avatar colors */
$avatarColors = [
    'SR' => '#6a0f70',
    'PM' => '#1877F2',
    'NK' => '#e67e22',
];
@endphp

{{-- ─── TOOLBAR ──────────────────────────────────────────────────────────── --}}
<div style="
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:16px;
">
    <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-family:'Inter',sans-serif; font-size:13px; color:#5a4868;">
            <strong style="color:#1e0a2c;">36</strong> pieces total across 6 stages
        </span>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        {{-- View toggles (visual only) --}}
        <button style="
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 12px; border-radius:6px;
            background:#6a0f70; border:none;
            font-family:'Inter',sans-serif; font-size:12px; font-weight:500;
            color:#fff; cursor:pointer;
        ">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Board
        </button>
        <button style="
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 12px; border-radius:6px;
            background:#f9f3fa; border:1px solid rgba(185,92,183,0.2);
            font-family:'Inter',sans-serif; font-size:12px; font-weight:400;
            color:#5a4868; cursor:pointer;
        ">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            List
        </button>
        <button style="
            display:inline-flex; align-items:center; gap:5px;
            padding:6px 12px; border-radius:6px;
            background:linear-gradient(135deg,#6a0f70,#b95cb7); border:none;
            font-family:'Inter',sans-serif; font-size:12px; font-weight:500;
            color:#fff; cursor:pointer;
        ">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Content
        </button>
    </div>
</div>

{{-- ─── KANBAN BOARD ─────────────────────────────────────────────────────── --}}
{{-- Horizontal scroll wrapper --}}
<div style="
    overflow-x:auto;
    padding-bottom:12px;
    margin: 0 -2px;
">
<div style="
    display:flex; gap:14px; align-items:flex-start;
    min-width:max-content;
    padding:0 2px;
">

@foreach($kanban as $colKey => $col)
{{-- ── COLUMN ──────────────────────────────────────────────────── --}}
<div style="
    width:220px; flex-shrink:0;
    background:#f8f4fc;
    border:1px solid rgba(185,92,183,0.12);
    border-radius:10px;
    overflow:hidden;
">

    {{-- Column Header --}}
    <div style="
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 12px 9px;
        border-bottom:1px solid rgba(185,92,183,0.1);
        background:#fff;
    ">
        <div style="display:flex; align-items:center; gap:7px;">
            {{-- Colour dot --}}
            <span style="
                width:9px; height:9px; border-radius:50%;
                background:{{ $col['color'] }};
                display:inline-block; flex-shrink:0;
            "></span>
            <span style="
                font-family:'Inter',sans-serif;
                font-size:12px; font-weight:600;
                color:#1e0a2c;
            ">{{ $col['label'] }}</span>
            {{-- Count badge --}}
            <span style="
                background:{{ $col['bg'] }};
                color:{{ $col['color'] }};
                font-family:'Inter',sans-serif;
                font-size:10.5px; font-weight:700;
                padding:1px 7px; border-radius:10px;
            ">{{ $col['count'] }}</span>
        </div>
        {{-- + Add button --}}
        <button style="
            display:inline-flex; align-items:center;
            width:22px; height:22px; border-radius:5px;
            background:{{ $col['bg'] }}; border:1px solid {{ $col['color'] }}33;
            color:{{ $col['color'] }}; cursor:pointer;
            justify-content:center;
        " title="Add to {{ $col['label'] }}">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
    </div>

    {{-- Cards --}}
    <div style="padding:8px; display:flex; flex-direction:column; gap:8px;">

        @foreach($col['cards'] as $card)
        @php
            $pl  = $platforms[$card['platform']] ?? ['bg'=>'#888','label'=>'?'];
            $tb  = $typeBadge[$card['type']]     ?? ['bg'=>'#eee','text'=>'#555'];
            $av  = $avatarColors[$card['assignee']] ?? '#888';
        @endphp
        {{-- Card --}}
        <div style="
            background:#ffffff;
            border:1px solid rgba(185,92,183,0.1);
            border-radius:8px;
            padding:10px 10px 9px;
            cursor:pointer;
            transition:box-shadow 150ms;
        "
        onmouseover="this.style.boxShadow='0 2px 10px rgba(106,15,112,0.1)'"
        onmouseout="this.style.boxShadow='none'"
        >
            {{-- Top row: platform bubble + type badge + "…" --}}
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:7px;">
                <div style="display:flex; align-items:center; gap:6px;">
                    {{-- Platform icon --}}
                    <span style="
                        width:18px; height:18px; border-radius:4px;
                        background:{{ $pl['bg'] }};
                        display:inline-flex; align-items:center; justify-content:center;
                        font-size:8px; font-weight:700; color:#fff;
                        flex-shrink:0;
                    ">{{ $pl['label'] }}</span>
                    {{-- Content type badge --}}
                    <span style="
                        background:{{ $tb['bg'] }};
                        color:{{ $tb['text'] }};
                        font-family:'Inter',sans-serif;
                        font-size:9.5px; font-weight:600;
                        padding:1px 6px; border-radius:8px;
                    ">{{ $card['type'] }}</span>
                </div>
                {{-- "…" menu --}}
                <button style="
                    background:transparent; border:none; cursor:pointer;
                    color:#b9a5c5; padding:0 2px; line-height:1;
                    font-size:14px; display:flex; align-items:center;
                " title="More options">•••</button>
            </div>

            {{-- Title --}}
            <p style="
                font-family:'Inter',sans-serif;
                font-size:12px; font-weight:500;
                color:#1e0a2c; margin:0 0 8px;
                line-height:1.4;
            ">{{ $card['title'] }}</p>

            {{-- Bottom: date + assignee --}}
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <span style="
                    font-family:'Inter',sans-serif;
                    font-size:10.5px; color:#9b6aad;
                ">{{ $card['date'] }}</span>
                <span style="
                    width:20px; height:20px; border-radius:50%;
                    background:{{ $av }};
                    display:inline-flex; align-items:center; justify-content:center;
                    font-family:'Inter',sans-serif;
                    font-size:8.5px; font-weight:700; color:#fff;
                " title="{{ $card['assignee'] }}">{{ $card['assignee'] }}</span>
            </div>
        </div>
        @endforeach

        {{-- Overflow link --}}
        @if($col['overflow'] > 0)
        <button style="
            background:transparent; border:1px dashed rgba(185,92,183,0.25);
            border-radius:7px; padding:7px; width:100%;
            font-family:'Inter',sans-serif; font-size:11.5px;
            color:#9b6aad; cursor:pointer; text-align:center;
        "
        onmouseover="this.style.borderColor='#b95cb7'; this.style.color='#6a0f70'"
        onmouseout="this.style.borderColor='rgba(185,92,183,0.25)'; this.style.color='#9b6aad'"
        >
            +{{ $col['overflow'] }} more {{ $col['overflow_label'] }}
        </button>
        @endif

    </div>{{-- /cards --}}
</div>{{-- /column --}}
@endforeach

</div>{{-- /kanban inner --}}
</div>{{-- /scroll wrapper --}}
