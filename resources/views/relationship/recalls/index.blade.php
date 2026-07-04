{{--
|==========================================================================
| PRE — Recall Pipeline (Phase 1 · Workstream D, slice 3)
| Route: GET /relationship/recalls   [relationship.recalls]
|
| Read-only. Recall rows from the legacy communication_queue, grouped by the
| RELIABLE legacy status. Additive — the legacy Communication List is untouched.
| Variables from RecallPipelineController@index:
|   $columns, $total, $openCount, $overdueCount
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Recall Pipeline')

@section('relationship-content')
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Recall Pipeline</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Patients due to return, by status.
            </p>
        </div>
        <button type="button" onclick="rpOpenAddRecall()"
           style="background:#fff;color:#534AB7;border:1px solid #EEEDFE;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
           + Add Recall
        </button>
    </div>

    {{-- Flash banner --}}
    @if (session('success'))
        <div style="background:#EAF3DE;border:1px solid #c3dba0;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#3B6D11;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Headline stats --}}
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:20px;color:#6b7280;font-size:13px;">
        <span>Total recalls: <strong style="color:#1f2937;">{{ number_format($total) }}</strong></span>
        <span>Open: <strong style="color:#1f2937;">{{ number_format($openCount) }}</strong></span>
        <span>Overdue: <strong style="color:{{ $overdueCount > 0 ? '#8A1F1F' : '#1f2937' }};">{{ number_format($overdueCount) }}</strong></span>
    </div>

    {{-- Kanban board (horizontal scroll) --}}
    <div style="display:flex;gap:14px;overflow-x:auto;padding-bottom:12px;align-items:flex-start;">
        @foreach ($columns as $col)
            <div style="flex:0 0 264px;width:264px;background:#f7f8fa;border:1px solid #eceef2;border-radius:12px;">

                {{-- Column header --}}
                <div style="padding:12px 14px;border-bottom:1px solid #eceef2;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span style="display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:13px;color:{{ $col['color'] }};">
                            <span style="width:9px;height:9px;border-radius:999px;background:{{ $col['color'] }};display:inline-block;"></span>
                            {{ $col['label'] }}
                        </span>
                        <span style="background:{{ $col['bg'] }};color:{{ $col['color'] }};font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:999px;">
                            {{ $col['count'] }}
                        </span>
                    </div>
                </div>

                {{-- Cards --}}
                <div style="padding:10px;display:flex;flex-direction:column;gap:9px;min-height:60px;">
                    @forelse ($col['items'] as $recall)
                        @php
                            $due       = $recall->follow_up_date ?? $recall->due_at;
                            $isOverdue = ($recall->is_overdue || $recall->status === 'overdue') && $recall->status !== 'closed';
                        @endphp

                        <div style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;">
                            <div style="font-weight:600;font-size:13px;color:#1f2937;">
                                {{ $recall->person_name ?: 'Unnamed' }}
                            </div>

                            {{-- Meta --}}
                            <div style="margin-top:5px;color:#6b7280;font-size:11.5px;line-height:1.55;">
                                @if ($recall->phone)<div>{{ $recall->phone }}</div>@endif
                                @if ($recall->channel)<div>{{ \App\Models\CommunicationQueue::CHANNELS[$recall->channel] ?? ucfirst($recall->channel) }}</div>@endif
                            </div>

                            {{-- Chips --}}
                            <div style="margin-top:7px;display:flex;flex-wrap:wrap;gap:5px;">
                                @if ($due)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;
                                        background:{{ $isOverdue ? '#FDECEC' : '#f0f1f3' }};
                                        color:{{ $isOverdue ? '#8A1F1F' : '#6b7280' }};">
                                        {{ $isOverdue ? 'Overdue · ' : '' }}{{ \Illuminate\Support\Carbon::parse($due)->format('d M') }}
                                    </span>
                                @endif
                                @if (($recall->attempt_count ?? 0) > 0)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#f0f1f3;color:#6b7280;">
                                        {{ $recall->attempt_count }} attempt{{ $recall->attempt_count > 1 ? 's' : '' }}
                                    </span>
                                @endif
                                @if ($recall->assigned_to)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                                        {{ $recall->assigned_to }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="color:#c2c6cd;font-size:12px;text-align:center;padding:14px 0;">No recalls</div>
                    @endforelse

                    @if ($col['hidden'] > 0)
                        <div style="color:#9ca3af;font-size:11.5px;text-align:center;padding:4px 0;">
                            +{{ $col['hidden'] }} more
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p style="margin-top:16px;color:#9ca3af;font-size:12px;">
        PRE (Relationship Platform). Recalls are read from the legacy communication queue
        (grouped by the reliable legacy status). The legacy Communication List remains available and unchanged.
        The 6 automated triggers are untouched — "+ Add Recall" writes a manually-tagged row into the same queue.
    </p>

    {{-- ══════════════════════════════════════════════════════════════════
         Add Recall modal — patient search (reuses the same
         relationship.search typeahead the topbar search uses) + priority +
         follow-up date + note. Posts to relationship.recalls.store, which
         writes into communication_queue via RecallEngineService::createManual()
         — the exact same table/defaults the 6 automated triggers use.
    ══════════════════════════════════════════════════════════════════ --}}
    @php $addRecallFailed = $errors->any(); @endphp
    <div id="rpAddRecallModal" style="display:{{ $addRecallFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Add Recall</h3>
                <button type="button" onclick="rpCloseAddRecall()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Manually queue a patient for a recall call.</p>

            @if ($addRecallFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.recalls.store') }}">
                @csrf

                <div style="margin-bottom:16px;position:relative;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Patient <span style="color:#c0392b;">*</span>
                    </label>
                    <input type="text" id="rpPatientSearch" placeholder="Search by name or phone…" autocomplete="off"
                           oninput="rpSearchPatients(this.value)"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    <input type="hidden" name="patient_id" id="rpPatientId" value="{{ old('patient_id') }}">
                    <div id="rpPatientResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;max-height:220px;overflow-y:auto;z-index:5;box-shadow:0 4px 14px rgba(0,0,0,.08);"></div>
                    <div id="rpPatientSelected" style="display:none;margin-top:8px;padding:8px 10px;background:#EEEDFE;border-radius:8px;font-size:12.5px;color:#534AB7;">
                        <span id="rpPatientSelectedName"></span>
                        <button type="button" onclick="rpClearPatient()" style="float:right;background:none;border:none;color:#534AB7;cursor:pointer;font-weight:600;">Change</button>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Priority <span style="color:#c0392b;">*</span>
                    </label>
                    <select name="priority" required
                            style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Follow-up Date <span style="color:#c0392b;">*</span>
                    </label>
                    <input type="date" name="follow_up_date" required value="{{ old('follow_up_date', today()->toDateString()) }}"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Note
                    </label>
                    <textarea name="note" rows="3" placeholder="Why is this patient being recalled?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;">{{ old('note') }}</textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit"
                            style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">
                        Add to Pipeline
                    </button>
                    <button type="button" onclick="rpCloseAddRecall()"
                            style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function rpOpenAddRecall() {
    document.getElementById('rpAddRecallModal').style.display = 'flex';
}
function rpCloseAddRecall() {
    document.getElementById('rpAddRecallModal').style.display = 'none';
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') rpCloseAddRecall();
});
document.getElementById('rpAddRecallModal')?.addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
});

function rpClearPatient() {
    document.getElementById('rpPatientId').value = '';
    document.getElementById('rpPatientSelected').style.display = 'none';
    document.getElementById('rpPatientSearch').style.display = 'block';
    document.getElementById('rpPatientSearch').value = '';
    document.getElementById('rpPatientSearch').focus();
}

let rpSearchTimer = null;
function rpSearchPatients(q) {
    clearTimeout(rpSearchTimer);
    const box = document.getElementById('rpPatientResults');

    if (q.trim().length < 3) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    rpSearchTimer = setTimeout(function () {
        fetch("{{ route('relationship.search') }}?q=" + encodeURIComponent(q))
            .then(r => r.json())
            .then(function (results) {
                // Only patients (not lead-only relationships) can be recalled.
                const withPatient = results.filter(r => r.patient_id);
                box.innerHTML = '';

                if (withPatient.length === 0) {
                    box.innerHTML = '<div style="padding:10px 12px;font-size:12.5px;color:#9ca3af;">No matching patients found.</div>';
                    box.style.display = 'block';
                    return;
                }

                withPatient.forEach(function (r) {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:9px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid #f3f4f6;';
                    row.innerHTML = '<div style="font-weight:600;color:#1f2937;">' + (r.name || 'Unnamed') + '</div>' +
                                    '<div style="font-size:11.5px;color:#9ca3af;">' + (r.meta || '') + '</div>';
                    row.onmouseenter = () => row.style.background = '#f7f8fa';
                    row.onmouseleave = () => row.style.background = '#fff';
                    row.onclick = function () {
                        rpSelectPatient(r);
                    };
                    box.appendChild(row);
                });
                box.style.display = 'block';
            })
            .catch(() => { box.style.display = 'none'; });
    }, 250);
}

function rpSelectPatient(r) {
    document.getElementById('rpPatientId').value = r.patient_id || '';
    document.getElementById('rpPatientSelectedName').textContent = r.name + (r.phone ? ' — ' + r.phone : '');
    document.getElementById('rpPatientSelected').style.display = 'block';
    document.getElementById('rpPatientSearch').style.display = 'none';
    document.getElementById('rpPatientResults').style.display = 'none';
    document.getElementById('rpPatientResults').innerHTML = '';
}
</script>
@endsection
