@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Relationship Journey — PRM')

@section('communication-content')

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;position:relative;z-index:10;">
    <a href="/communication" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication
    </a>
</div>

{{-- ── NAV TABS ─────────────────────────────────────────────────────── --}}
<div class="prm-topbar" style="margin-top:0;">
    <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
</div>

{{-- ── PAGE HEADER ────────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">
            Relationship Journey
            <i class="ti ti-info-circle" aria-hidden="true"
               title="Visualize and manage relationship journeys across all stages"></i>
        </h1>
        <p class="page-sub">Visualize and manage leads across all stages</p>
    </div>
    <div class="page-actions">
        <div class="btn-segment">
            <button class="seg-btn active" id="btnBoard" onclick="switchView('board')">
                <i class="ti ti-layout-grid" aria-hidden="true"></i> Board View
            </button>
            <button class="seg-btn" id="btnList" onclick="switchView('list')">
                <i class="ti ti-list" aria-hidden="true"></i> List View
            </button>
        </div>
        <a href="{{ route('prm.inbox') }}" class="btn-outline-sm" style="text-decoration:none;">
            <i class="ti ti-inbox" aria-hidden="true"></i> Things to Do
        </a>
        <a href="{{ route('prm.chatbot') }}" class="btn-outline-sm" style="text-decoration:none;">
            <i class="ti ti-message-chatbot" aria-hidden="true"></i> Chatbot
        </a>
        <a href="{{ route('prm.source-analytics') }}" class="btn-outline-sm" style="text-decoration:none;">
            <i class="ti ti-chart-bar" aria-hidden="true"></i> Source Analytics
        </a>
        <a href="{{ route('prm.reports.team') }}" class="btn-outline-sm" style="text-decoration:none;">
            <i class="ti ti-users" aria-hidden="true"></i> Team Report
        </a>
        <button class="btn-outline-sm" onclick="openFilters()">
            <i class="ti ti-filter" aria-hidden="true"></i> Filters
        </button>
        <div class="btn-add-group">
            <a href="{{ route('prm.quick-add') }}" class="btn-primary-sm" style="text-decoration:none;">
                <i class="ti ti-bolt" aria-hidden="true"></i> Quick Add
            </a>
            <button class="btn-primary-caret" onclick="toggleAddMenu()">
                <i class="ti ti-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="add-dropdown" id="addDropdown" style="display:none">
                <a href="{{ route('prm.quick-add') }}" style="text-decoration:none;display:block;" onclick="closeAddMenu()">
                    <i class="ti ti-bolt" aria-hidden="true"></i> Quick Add (4 fields)
                </a>
                <a href="{{ route('prm.add-lead') }}" style="text-decoration:none;display:block;" onclick="closeAddMenu()">
                    <i class="ti ti-user-plus" aria-hidden="true"></i> Full Lead Form
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── STATS BAR ───────────────────────────────────────────────────── --}}
<div class="stats-bar">
    <div class="stat-card">
        <div class="stat-icon si-blue"><i class="ti ti-users" aria-hidden="true"></i></div>
        <div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-number">{{ $stats['total'] }}</div>
            <div class="stat-trend trend-up">↑ 12% vs last month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon si-teal"><i class="ti ti-user-check" aria-hidden="true"></i></div>
        <div>
            <div class="stat-label">Converted</div>
            <div class="stat-number">{{ $stats['converted'] }}</div>
            <div class="stat-trend trend-up">↑ 18% vs last month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon si-purple"><i class="ti ti-chart-bar" aria-hidden="true"></i></div>
        <div>
            <div class="stat-label">In Pipeline</div>
            <div class="stat-number">{{ $stats['in_pipeline'] }}</div>
            <div class="stat-trend trend-up">↑ 8% vs last month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon si-coral"><i class="ti ti-shield-x" aria-hidden="true"></i></div>
        <div>
            <div class="stat-label">Lost</div>
            <div class="stat-number">{{ $stats['lost'] }}</div>
            <div class="stat-trend trend-dn">↓ 5% vs last month</div>
        </div>
    </div>
</div>

{{-- ── BOARD VIEW ──────────────────────────────────────────────────── --}}
<div id="boardView" class="board-scroll">
    <div class="pipeline-board" id="pipelineBoard">
        @foreach($stages as $stageKey => $stageInfo)
            <x-prm.pipeline-column
                :stageKey="$stageKey"
                :stageLabel="$stageInfo['label']"
                :color="$stageInfo['color']"
                :leads="$grouped->get($stageKey, collect())"
                :totalCount="$stageKey === 'converted' ? 26 : 0"
            />
        @endforeach
    </div>
</div>

{{-- ── LIST VIEW ───────────────────────────────────────────────────── --}}
<div id="listView" style="display:none" class="list-view-wrap">
    @include('communication.prm.board')
</div>

{{-- ── PIPELINE SUMMARY ─────────────────────────────────────────────── --}}
<div class="pipeline-summary">
    <div class="make-a-call" onclick="window.location='/communication/call-manager'">
        <i class="ti ti-phone" aria-hidden="true"></i>
        <div>
            <div class="mac-label">Make a Call</div>
            <div class="mac-sub">Quick dialer</div>
        </div>
        <i class="ti ti-arrow-right" aria-hidden="true"></i>
    </div>

    <div class="summary-title">Pipeline Summary</div>
    <div class="donut-chart-wrap">
        <canvas id="pipelineDonut" width="80" height="80"></canvas>
    </div>
    <div class="summary-legend">
        @php
        $legendItems = [
            ['label' => 'New Lead',     'color' => '#185FA5', 'count' => $grouped->get('new_lead', collect())->count(),     'total' => $stats['total']],
            ['label' => 'Contacted',    'color' => '#0F6E56', 'count' => $grouped->get('contacted', collect())->count(),    'total' => $stats['total']],
            ['label' => 'Appointment',  'color' => '#854F0B', 'count' => $grouped->get('appointment', collect())->count(),  'total' => $stats['total']],
            ['label' => 'Consultation', 'color' => '#534AB7', 'count' => $grouped->get('consultation', collect())->count(), 'total' => $stats['total']],
            ['label' => 'Plan Given',   'color' => '#3B6D11', 'count' => $grouped->get('plan_given', collect())->count(),   'total' => $stats['total']],
            ['label' => 'Converted',    'color' => '#1D9E75', 'count' => 26,                                                'total' => $stats['total']],
            ['label' => 'Lost',         'color' => '#E24B4A', 'count' => $grouped->get('lost', collect())->count(),         'total' => $stats['total']],
        ];
        @endphp
        @foreach($legendItems as $item)
            <div class="legend-item">
                <span class="legend-dot" style="background:{{ $item['color'] }}"></span>
                {{ $item['label'] }}
                {{ $item['count'] }}
                ({{ $item['total'] > 0 ? number_format(($item['count'] / $item['total']) * 100, 1) : 0 }}%)
            </div>
        @endforeach
        <div class="legend-item legend-total">
            <strong>Total {{ $stats['total'] }} (100%)</strong>
        </div>
    </div>
    <div class="summary-hints">
        <div class="sum-hint"><i class="ti ti-arrows-move" aria-hidden="true"></i> Drag & Drop to move leads between stages</div>
        <div class="sum-hint"><i class="ti ti-info-circle" aria-hidden="true"></i> Click on a lead card to view details and take action</div>
    </div>
</div>

{{-- ── FILTERS MODAL ───────────────────────────────────────────────── --}}
<div id="filtersModal" class="modal-overlay" style="display:none" onclick="closeFilters()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-head">
            <span class="modal-title">Filters</span>
            <button class="modal-close" onclick="closeFilters()"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Stage</label>
                <select class="form-select" id="filterStage">
                    <option value="">All Stages</option>
                    <option>New Lead</option><option>Contacted</option><option>Appointment</option>
                    <option>Consultation</option><option>Plan Given</option><option>Converted</option><option>Lost</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Source</label>
                <select class="form-select" id="filterSource">
                    <option value="">All Sources</option>
                    <option>WhatsApp</option><option>Instagram</option><option>Google</option>
                    <option>Walk-in</option><option>Call Manager</option><option>Referral</option><option>Website</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select class="form-select" id="filterAssigned">
                    <option value="">All Staff</option>
                    <option>Neha (Front Desk)</option>
                    <option>Anjali Kapoor (Coordinator)</option>
                    <option>Priya Singh (Front Desk)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Urgency</label>
                <select class="form-select" id="filterUrgency">
                    <option value="">All</option>
                    <option>High</option><option>Medium</option><option>Low</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment Interest</label>
                <select class="form-select" id="filterTreatment">
                    <option value="">All Treatments</option>
                    <option>Dental Implant</option><option>Teeth Whitening</option>
                    <option>Braces / Aligners</option><option>Root Canal</option><option>Smile Makeover</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-ghost" onclick="clearFilters()">Clear All</button>
            <button class="btn-primary-sm" onclick="applyFilters()">Apply Filters</button>
        </div>
    </div>
</div>

@endsection

@push('communication-scripts')
<script src="{{ asset('js/communication/prm-board.js') }}"></script>
<script>
const pipelineLegendData = {
    labels: ['New Lead','Contacted','Appointment','Consultation','Plan Given','Converted','Lost'],
    colors: ['#185FA5','#0F6E56','#854F0B','#534AB7','#3B6D11','#1D9E75','#E24B4A'],
    counts: [
        {{ $grouped->get('new_lead', collect())->count() }},
        {{ $grouped->get('contacted', collect())->count() }},
        {{ $grouped->get('appointment', collect())->count() }},
        {{ $grouped->get('consultation', collect())->count() }},
        {{ $grouped->get('plan_given', collect())->count() }},
        26,
        {{ $grouped->get('lost', collect())->count() }}
    ]
};
if (window.initDonutChart) initDonutChart('pipelineDonut', pipelineLegendData);

function switchView(v) {
    document.getElementById('boardView').style.display = v==='board' ? '' : 'none';
    document.getElementById('listView').style.display  = v==='list'  ? '' : 'none';
    document.getElementById('btnBoard').classList.toggle('active', v==='board');
    document.getElementById('btnList').classList.toggle('active',  v==='list');
}

function toggleAddMenu() {
    const d = document.getElementById('addDropdown');
    d.style.display = d.style.display==='none' ? 'block' : 'none';
}
function closeAddMenu() { document.getElementById('addDropdown').style.display='none'; }
document.addEventListener('click', e=>{ if (!e.target.closest('.btn-add-group')) closeAddMenu(); });

function openFilters()  { document.getElementById('filtersModal').style.display='flex'; }
function closeFilters() { document.getElementById('filtersModal').style.display='none'; }
function clearFilters() {
    ['filterStage','filterSource','filterAssigned','filterUrgency','filterTreatment']
        .forEach(id=>{ document.getElementById(id).value=''; });
}
function applyFilters() { closeFilters(); }
</script>
@endpush