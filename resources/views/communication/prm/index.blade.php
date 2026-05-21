@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Pipeline Board — PRM')

@section('communication-content')

{{-- ── NAV TABS ─────────────────────────────────────────────────────── --}}
<div class="prm-topbar">
    <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
</div>

{{-- ── PAGE HEADER ────────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">
            Pipeline Board
            <i class="ti ti-info-circle" aria-hidden="true"
               title="Visualize and manage all leads across pipeline stages"></i>
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
        <button class="btn-outline-sm" onclick="openFilters()">
            <i class="ti ti-filter" aria-hidden="true"></i> Filters
        </button>
        <div class="btn-add-group">
            <button class="btn-primary-sm" onclick="openAddLeadModal()">
                <i class="ti ti-plus" aria-hidden="true"></i> Add Lead
            </button>
            <button class="btn-primary-caret" onclick="toggleAddMenu()">
                <i class="ti ti-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="add-dropdown" id="addDropdown" style="display:none">
                <button onclick="openAddLeadModal(); closeAddMenu()">
                    <i class="ti ti-user-plus" aria-hidden="true"></i> Full Lead Form
                </button>
                <button onclick="openQuickAdd(); closeAddMenu()">
                    <i class="ti ti-bolt" aria-hidden="true"></i> Quick Add Lead
                </button>
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


{{-- ════════════════════════════════════════════════════════════
     MODAL: MARK AS LOST
     ════════════════════════════════════════════════════════════ --}}
<div id="lostModal" class="modal-overlay" style="display:none" onclick="closeLostModal(event)">
    <div class="modal-box" style="max-width:440px;width:95%;" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:#FCEBEB;color:#A32D2D;display:flex;align-items:center;justify-content:center;">
                    <i class="ti ti-x" style="font-size:16px;"></i>
                </div>
                <div>
                    <div class="modal-title">Mark as Lost</div>
                    <div style="font-size:11px;color:#9A9A94;" id="lostLeadName">—</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeLostModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <input type="hidden" id="lostLeadId" value="">
            <div class="form-group">
                <label class="form-label">Reason for Loss <span class="req">*</span></label>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                    @foreach([
                        ['cost',           'ti-currency-rupee', 'Cost / Budget Issue',        'Patient found it too expensive'],
                        ['not_interested',  'ti-thumb-down',     'Not Interested',             'Changed mind or no longer wants treatment'],
                        ['not_now',         'ti-clock',          'Not Right Now',              'Interested but timing is not right'],
                        ['chose_other',     'ti-building',       'Chose Another Clinic',       'Went to a different provider'],
                        ['no_response',     'ti-phone-off',      'No Response / Unreachable',  'Multiple attempts, no reply'],
                        ['other',           'ti-dots-circle',    'Other',                      'Any other reason'],
                    ] as [$val, $icon, $label, $sub])
                    <label class="lost-reason-card" data-value="{{ $val }}">
                        <input type="radio" name="lost_reason" value="{{ $val }}" style="display:none;">
                        <div style="width:32px;height:32px;border-radius:8px;background:#F7F6F3;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="ti {{ $icon }}" style="font-size:15px;color:#5A5A56;"></i>
                        </div>
                        <div>
                            <div style="font-size:12px;font-weight:500;color:#1A1A18;">{{ $label }}</div>
                            <div style="font-size:11px;color:#9A9A94;">{{ $sub }}</div>
                        </div>
                        <i class="ti ti-check lost-check" style="margin-left:auto;font-size:14px;color:#3B29C8;display:none;"></i>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="form-group" style="margin-top:14px;">
                <label class="form-label">Additional Notes (Optional)</label>
                <textarea id="lostNotes" class="form-textarea" rows="3"
                          placeholder="Any additional context about why this lead was lost..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-ghost" onclick="closeLostModal()">Cancel</button>
            <button class="btn-danger-sm" onclick="confirmMarkLost()">
                <i class="ti ti-x"></i> Mark as Lost
            </button>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════
     MODAL: DELETE CONFIRM
     ════════════════════════════════════════════════════════════ --}}
<div id="deleteModal" class="modal-overlay" style="display:none" onclick="closeDeleteModal(event)">
    <div class="modal-box" style="max-width:380px;width:95%;" onclick="event.stopPropagation()">
        <div class="modal-head">
            <span class="modal-title">Delete Lead</span>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-body" style="padding:20px;text-align:center;">
            <div style="width:56px;height:56px;border-radius:50%;background:#FCEBEB;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <i class="ti ti-trash" style="font-size:24px;color:#A32D2D;"></i>
            </div>
            <div style="font-size:15px;font-weight:500;color:#1A1A18;margin-bottom:6px;">Delete "<span id="deleteLeadName">this lead</span>"?</div>
            <div style="font-size:12px;color:#5A5A56;line-height:1.6;">This will permanently remove the lead and all associated activity. This action cannot be undone.</div>
            <input type="hidden" id="deleteLeadId" value="">
        </div>
        <div class="modal-footer">
            <button class="btn-ghost" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-danger-sm" onclick="confirmDelete()">
                <i class="ti ti-trash"></i> Yes, Delete
            </button>
        </div>
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

{{-- Context menu --}}
<div id="leadContextMenu" class="lead-context-menu" style="display:none;">
    <button class="ctx-item" id="ctxMoveNext" onclick="ctxMoveToNext()">
        <i class="ti ti-arrow-right"></i> Move to Next Stage
    </button>
    <button class="ctx-item" onclick="ctxEditLead()">
        <i class="ti ti-edit"></i> Edit Lead
    </button>
    <div class="ctx-divider"></div>
    <button class="ctx-item ctx-danger" onclick="ctxMarkLost()">
        <i class="ti ti-x"></i> Mark as Lost
    </button>
    <button class="ctx-item ctx-danger" onclick="ctxDeleteLead()">
        <i class="ti ti-trash"></i> Delete Lead
    </button>
</div>

@endsection

@push('communication-scripts')
<script src="{{ asset('js/communication/prm-board.js') }}"></script>
<script>
const STAGE_ORDER  = ['new_lead','contacted','appointment','consultation','plan_given','converted'];
const STAGE_LABELS = {
    new_lead:'New Lead', contacted:'Contacted', appointment:'Appointment',
    consultation:'Consultation', plan_given:'Plan Given', converted:'Converted', lost:'Lost'
};

/* Donut */
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

/* ── VIEW TOGGLE ─────────────────────────────────────────── */
function switchView(v) {
    document.getElementById('boardView').style.display = v==='board' ? '' : 'none';
    document.getElementById('listView').style.display  = v==='list'  ? '' : 'none';
    document.getElementById('btnBoard').classList.toggle('active', v==='board');
    document.getElementById('btnList').classList.toggle('active',  v==='list');
}

/* ── ADD LEAD MODAL ──────────────────────────────────────── */
function openAddLeadModal(prefillStage) {
    const modal = document.getElementById('addLeadModal');
    document.getElementById('leadModalForm').reset();
    document.getElementById('leadModalForm').action = "{{ route('prm.store-lead') }}";
    document.getElementById('formMethod').value  = 'POST';
    document.getElementById('formLeadId').value  = '';
    document.getElementById('modalLeadTitle').textContent = 'Add Lead';
    document.getElementById('modalLeadSub').textContent   = 'Add a new lead to start follow-up';
    document.getElementById('mSaveBtnLabel').textContent  = 'Save Lead';
    document.getElementById('mSaveAnother').style.display = '';
    document.getElementById('mDeleteBtn').style.display   = 'none';
    document.getElementById('mNoteCount').textContent = '0';
    const wrap = document.getElementById('mTagsWrap');
    wrap.querySelectorAll('.tag-chip').forEach(c=>c.remove());
    document.getElementById('mTagsInput').value = '[]';
    document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('selected'));
    document.getElementById('typeCardNew').classList.add('selected');
    const t = new Date(); t.setDate(t.getDate()+1);
    document.getElementById('mFollowupDate').value = t.toISOString().split('T')[0];
    document.getElementById('mFollowupTime').value = '11:00';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function openEditLeadModal(d) {
    openAddLeadModal();
    document.getElementById('modalLeadTitle').textContent = 'Edit Lead';
    document.getElementById('modalLeadSub').textContent   = 'Update lead information and follow-up details';
    document.getElementById('mSaveBtnLabel').textContent  = 'Save Changes';
    document.getElementById('mSaveAnother').style.display = 'none';
    document.getElementById('mDeleteBtn').style.display   = '';
    document.getElementById('formMethod').value  = 'PUT';
    document.getElementById('formLeadId').value  = d.id;
    document.getElementById('leadModalForm').action = '/communication/prm/leads/' + d.id;
    document.getElementById('mName').value       = d.name        || '';
    document.getElementById('mPhone').value      = d.phone       || '';
    document.getElementById('mEmail').value      = d.email       || '';
    document.getElementById('mDob').value        = d.dob         || '';
    document.getElementById('mReferredBy').value = d.referred_by || '';
    document.getElementById('mOccupation').value = d.occupation  || '';
    document.getElementById('mLocation').value   = d.location    || '';
    document.getElementById('mNotes').value      = d.notes       || '';
    document.getElementById('mNoteCount').textContent = (d.notes||'').length;
    ['mGender','mTreatment','mSource','mLanguage','mPreferredTime','mContactMethod'].forEach(id => {
        const key = id.replace('m','').toLowerCase();
        setSelectValue(id, d[key]);
    });
    setSelectValue('mAssignedTo', d.assigned_to_id);
    setRadioValue('lead_type', d.lead_type || 'new_patient');
    setRadioValue('preferred_contact', d.preferred_contact || 'call');
    setRadioValue('urgency', d.urgency || 'medium');
    document.getElementById('mFollowupDate').value = d.followup_date || '';
    document.getElementById('mFollowupTime').value = d.followup_time || '11:00';
    document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('selected'));
    document.getElementById(d.lead_type==='existing_patient'?'typeCardExisting':'typeCardNew').classList.add('selected');
    const wrap = document.getElementById('mTagsWrap');
    const ti   = wrap.querySelector('.tags-text-input');
    wrap.querySelectorAll('.tag-chip').forEach(c=>c.remove());
    (d.tags||[]).forEach(tag=>{
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = tag+' <button type="button" onclick="modalRemoveTag(this)">×</button>';
        wrap.insertBefore(chip, ti);
    });
    document.getElementById('mTagsInput').value = JSON.stringify(d.tags||[]);
}

function closeAddLeadModal(e) {
    if (e && e.target !== document.getElementById('addLeadModal')) return;
    document.getElementById('addLeadModal').style.display = 'none';
    document.body.style.overflow = '';
}

function confirmModalDelete() {
    const id = document.getElementById('formLeadId').value;
    const nm = document.getElementById('mName').value;
    closeAddLeadModal();
    openDeleteModal(id, nm);
}

/* ── CONTEXT MENU ────────────────────────────────────────── */
let _ctxLead = null;

function openContextMenu(btn, d) {
    _ctxLead = d;
    const menu = document.getElementById('leadContextMenu');
    const rect = btn.getBoundingClientRect();
    menu.style.display = 'block';
    menu.style.position = 'fixed';
    menu.style.top  = (rect.bottom + 4) + 'px';
    menu.style.left = Math.max(8, rect.right - 204) + 'px';
    menu.style.zIndex = 9999;
    const idx  = STAGE_ORDER.indexOf(d.stage);
    const next = STAGE_ORDER[idx+1];
    const mb   = document.getElementById('ctxMoveNext');
    if (next) {
        mb.style.display = '';
        mb.innerHTML = `<i class="ti ti-arrow-right"></i> Move to <strong>${STAGE_LABELS[next]}</strong>`;
    } else {
        mb.style.display = 'none';
    }
    setTimeout(()=>document.addEventListener('click', _closeCtxOutside, {once:true}), 0);
}

function _closeCtxOutside(e) {
    if (!document.getElementById('leadContextMenu').contains(e.target)) closeContextMenu();
}
function closeContextMenu() {
    document.getElementById('leadContextMenu').style.display = 'none';
    _ctxLead = null;
}
function ctxMoveToNext() {
    if (!_ctxLead) return;
    const next = STAGE_ORDER[STAGE_ORDER.indexOf(_ctxLead.stage)+1];
    closeContextMenu();
    if (next) moveLeadToStage(_ctxLead.id, next);
}
function ctxEditLead()  { const d=_ctxLead; closeContextMenu(); openEditLeadModal(d); }
function ctxMarkLost()  { const d=_ctxLead; closeContextMenu(); openLostModal(d.id, d.name); }
function ctxDeleteLead(){ const d=_ctxLead; closeContextMenu(); openDeleteModal(d.id, d.name); }

/* ── MOVE LEAD (UI) ──────────────────────────────────────── */
function moveLeadToStage(leadId, newStage) {
    const card = document.querySelector(`.lead-card[data-lead-id="${leadId}"]`);
    const col  = document.querySelector(`.col-cards[data-stage="${newStage}"]`);
    if (!card || !col) return;
    card.style.transition = 'opacity 0.2s, transform 0.2s';
    card.style.opacity = '0'; card.style.transform = 'scale(0.95)';
    setTimeout(()=>{
        col.prepend(card);
        card.dataset.stage = newStage;
        card.style.opacity = '1'; card.style.transform = 'scale(1)';
        const pill = card.querySelector('.stage-pill');
        if (pill) { pill.textContent = STAGE_LABELS[newStage]; }
        updateColumnCounts();
        showToast('Moved to ' + STAGE_LABELS[newStage]);
    }, 200);
}

function updateColumnCounts() {
    document.querySelectorAll('.pipeline-col').forEach(col=>{
        const badge = col.querySelector('.col-count');
        if (badge) badge.textContent = col.querySelectorAll('.lead-card').length;
    });
}

/* ── LOST MODAL ──────────────────────────────────────────── */
function openLostModal(id, name) {
    document.getElementById('lostLeadId').value = id;
    document.getElementById('lostLeadName').textContent = name||'—';
    document.getElementById('lostNotes').value = '';
    document.querySelectorAll('.lost-reason-card').forEach(c=>{
        c.classList.remove('selected');
        c.querySelector('.lost-check').style.display='none';
        c.querySelector('input').checked=false;
    });
    document.getElementById('lostModal').style.display='flex';
    document.body.style.overflow='hidden';
}
function closeLostModal(e) {
    if (e && e.target!==document.getElementById('lostModal')) return;
    document.getElementById('lostModal').style.display='none';
    document.body.style.overflow='';
}
function confirmMarkLost() {
    const sel = document.querySelector('.lost-reason-card.selected');
    if (!sel) { showToast('Please select a reason', 'error'); return; }
    const id = document.getElementById('lostLeadId').value;
    closeLostModal();
    moveLeadToStage(id, 'lost');
    showToast('Lead marked as lost');
}

document.addEventListener('click', e=>{
    const c = e.target.closest('.lost-reason-card');
    if (!c) return;
    document.querySelectorAll('.lost-reason-card').forEach(x=>{
        x.classList.remove('selected');
        x.querySelector('.lost-check').style.display='none';
        x.querySelector('input').checked=false;
    });
    c.classList.add('selected');
    c.querySelector('.lost-check').style.display='';
    c.querySelector('input').checked=true;
});

/* ── DELETE MODAL ────────────────────────────────────────── */
function openDeleteModal(id, name) {
    document.getElementById('deleteLeadId').value = id;
    document.getElementById('deleteLeadName').textContent = name||'this lead';
    document.getElementById('deleteModal').style.display='flex';
    document.body.style.overflow='hidden';
}
function closeDeleteModal(e) {
    if (e && e.target!==document.getElementById('deleteModal')) return;
    document.getElementById('deleteModal').style.display='none';
    document.body.style.overflow='';
}
function confirmDelete() {
    const id = document.getElementById('deleteLeadId').value;
    closeDeleteModal();
    const card = document.querySelector(`.lead-card[data-lead-id="${id}"]`);
    if (card) {
        card.style.transition='opacity 0.2s,transform 0.2s';
        card.style.opacity='0'; card.style.transform='scale(0.9)';
        setTimeout(()=>{ card.remove(); updateColumnCounts(); }, 200);
    }
    showToast('Lead deleted');
}

/* ── FILTERS ─────────────────────────────────────────────── */
function openFilters()  { document.getElementById('filtersModal').style.display='flex'; }
function closeFilters() { document.getElementById('filtersModal').style.display='none'; }
function clearFilters() {
    ['filterStage','filterSource','filterAssigned','filterUrgency','filterTreatment']
        .forEach(id=>{ document.getElementById(id).value=''; });
}
function applyFilters() { closeFilters(); }

/* ── ADD DROPDOWN ────────────────────────────────────────── */
function toggleAddMenu() {
    const d = document.getElementById('addDropdown');
    d.style.display = d.style.display==='none' ? 'block' : 'none';
}
function closeAddMenu() { document.getElementById('addDropdown').style.display='none'; }
document.addEventListener('click', e=>{ if (!e.target.closest('.btn-add-group')) closeAddMenu(); });

/* ── FORM HELPERS ────────────────────────────────────────── */
document.getElementById('mNotes')?.addEventListener('input', function(){
    document.getElementById('mNoteCount').textContent = this.value.length;
});
document.querySelectorAll('.type-card input').forEach(r=>{
    r.addEventListener('change',function(){
        document.querySelectorAll('.type-card').forEach(c=>c.classList.remove('selected'));
        this.closest('.type-card').classList.add('selected');
    });
});
document.querySelectorAll('#addLeadModal .toggle-btn input, #addLeadModal .urgency-btn input').forEach(r=>{
    r.addEventListener('change',function(){
        this.closest('.toggle-group,.urgency-group').querySelectorAll('label').forEach(l=>l.classList.remove('active'));
        this.closest('label').classList.add('active');
    });
});
function modalAddTag(e) {
    if (e.key!=='Enter') return; e.preventDefault();
    const v = e.target.value.trim(); if (!v) return;
    const wrap = document.getElementById('mTagsWrap');
    const chip = document.createElement('span');
    chip.className='tag-chip';
    chip.innerHTML=v+' <button type="button" onclick="modalRemoveTag(this)">×</button>';
    wrap.insertBefore(chip, e.target);
    e.target.value=''; modalUpdateTags();
}
function modalRemoveTag(btn){ btn.closest('.tag-chip').remove(); modalUpdateTags(); }
function modalUpdateTags(){
    const tags=[...document.querySelectorAll('#mTagsWrap .tag-chip')]
        .map(c=>c.textContent.trim().replace('×','').trim());
    document.getElementById('mTagsInput').value=JSON.stringify(tags);
}
function setSelectValue(id,v){ const e=document.getElementById(id); if(e&&v) e.value=v; }
function setRadioValue(name,v){
    const r=document.querySelector(`input[name="${name}"][value="${v}"]`);
    if(r){r.checked=true; r.dispatchEvent(new Event('change'));}
}

/* ── TOAST ───────────────────────────────────────────────── */
function showToast(msg, type='success') {
    let t = document.getElementById('prmToast');
    if (!t) {
        t = document.createElement('div'); t.id='prmToast';
        t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:10px 20px;border-radius:8px;font-size:13px;font-weight:500;z-index:99999;transition:opacity 0.3s;pointer-events:none;white-space:nowrap;';
        document.body.appendChild(t);
    }
    t.textContent=msg;
    t.style.background = type==='error'?'#A32D2D':'#1A1A18';
    t.style.color='#fff'; t.style.opacity='1';
    clearTimeout(t._t);
    t._t=setTimeout(()=>t.style.opacity='0', 2500);
}
function openQuickAdd(){ openAddLeadModal(); }
</script>

<style>
.lost-reason-card {
    display:flex;align-items:center;gap:12px;padding:10px 12px;
    border:1px solid rgba(0,0,0,0.08);border-radius:8px;cursor:pointer;
    transition:border-color 0.15s,background 0.15s;background:#FFFFFF;
}
.lost-reason-card:hover { background:#F7F6F3; }
.lost-reason-card.selected { border-color:#3B29C8;background:#EEEDFE; }
.lead-context-menu {
    background:#FFFFFF;border:1px solid rgba(0,0,0,0.14);border-radius:10px;
    box-shadow:0 8px 24px rgba(0,0,0,0.12);padding:4px;min-width:204px;
}
.ctx-item {
    display:flex;align-items:center;gap:8px;width:100%;padding:9px 12px;
    font-size:12px;font-weight:500;color:#1A1A18;background:transparent;
    border:none;border-radius:6px;cursor:pointer;text-align:left;
}
.ctx-item:hover { background:#F7F6F3; }
.ctx-item.ctx-danger { color:#A32D2D; }
.ctx-item.ctx-danger:hover { background:#FCEBEB; }
.ctx-divider { height:1px;background:rgba(0,0,0,0.08);margin:4px 0; }
.modal-box .al-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
@media(max-width:640px){ .modal-box .al-form-grid { grid-template-columns:1fr; } }
.ctx-item:hover { background:#F7F6F3; }
.ctx-item.ctx-danger { color:#A32D2D; }
.ctx-item.ctx-danger:hover { background:#FCEBEB; }
.ctx-divider { height:1px;background:rgba(0,0,0,0.08);margin:4px 0; }
.modal-box .al-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
@media(max-width:640px){ .modal-box .al-form-grid { grid-template-columns:1fr; } }

.lead-context-menu {
    background:#FFFFFF;
    border:1px solid rgba(0,0,0,0.14);
    border-radius:10px;
    box-shadow:0 8px 24px rgba(0,0,0,0.12);
    padding:4px;
    min-width:180px;  /* was 204px */
    width:fit-content;
}
.ctx-item {
    display:flex;
    align-items:center;
    gap:8px;
    width:100%;
    padding:7px 10px;  /* was 9px 12px */
    font-size:12px;
    font-weight:500;
    color:#1A1A18;
    background:transparent;
    border:none;
    border-radius:6px;
    cursor:pointer;
    text-align:left;
    white-space:nowrap;
}

</style>

@endpush