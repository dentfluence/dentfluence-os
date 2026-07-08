{{--
    relationship/pipeline/_detail-card.blade.php

    Lead Detail modal — mirrors relationship/opportunities/_detail-card.blade.php
    (the "Opportunity Detail" popup). Rendered by
    LeadPipelineController::detailModal() and injected via AJAX into the
    "Lead Detail" popup on the pipeline board (index.blade.php).

    Added 2026-07-08 to close the gap Sumit flagged: Log Activity wrote a
    row (LeadActivity, via LeadPipelineController::logActivity()) but the
    board never showed it back — no way to see what was logged or who did it.

    Required variable: $lead (with `activities` eager-loaded, latest first)
--}}
@php
    $stageLabels = [
        'new_lead'    => ['label' => 'New Lead',    'color' => '#534AB7', 'bg' => '#EEEDFE'],
        'contacted'   => ['label' => 'Contacted',   'color' => '#0F6E56', 'bg' => '#E1F5EE'],
        'appointment' => ['label' => 'Appointment / Consultation', 'color' => '#854F0B', 'bg' => '#FAEEDA'],
        'plan_given'  => ['label' => 'Plan Given',  'color' => '#993556', 'bg' => '#FBEAF0'],
        'converted'   => ['label' => 'Converted',   'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        'lost'        => ['label' => 'Lost',        'color' => '#8A1F1F', 'bg' => '#FDECEC'],
    ];
    $stageInfo = $stageLabels[$lead->stage] ?? ['label' => ucwords(str_replace('_', ' ', $lead->stage)), 'color' => '#6b7280', 'bg' => '#f3f4f6'];
    $initials  = collect(explode(' ', $lead->name ?: 'U'))
                    ->map(fn($w) => strtoupper($w[0] ?? ''))
                    ->take(2)->implode('');
@endphp

{{-- Lead header --}}
<div style="display:flex;align-items:center;gap:14px;padding:20px 24px">
    <div style="width:44px;height:44px;border-radius:50%;background:#EEEDFE;color:#534AB7;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0">{{ $initials }}</div>
    <div style="flex:1;min-width:0">
        <h2 style="font-size:17px;font-weight:700;color:#1f2937;margin:0;font-family:'Cormorant Garamond',serif;">
            {{ $lead->name ?: 'Unnamed lead' }}
        </h2>
        <span style="font-size:13px;color:#6b7280">
            {{ $lead->phone }}
            @if($lead->phone && $lead->treatment) · @endif
            {{ $lead->treatment }}
        </span>
    </div>
    <span style="background:{{ $stageInfo['bg'] }};color:{{ $stageInfo['color'] }};padding:6px 12px;border-radius:20px;font-size:13px;font-weight:600;white-space:nowrap">
        {{ $stageInfo['label'] }}
    </span>
</div>

{{-- Details grid --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;padding:0;border-top:1px solid #f3f4f6">
    @php
        $fields = [
            ['label' => 'Stage',          'value' => $stageInfo['label']],
            ['label' => 'Urgency',        'value' => $lead->urgency ? ucfirst($lead->urgency) : '—'],
            ['label' => 'Lead Value',     'value' => $lead->lead_value ? '₹'.number_format((float) $lead->lead_value) : '—'],
            ['label' => 'Follow-up Date', 'value' => $lead->followup_date ? $lead->followup_date->format('d M Y') : '—'],
            ['label' => 'Assigned To',    'value' => $lead->assigned_to ?: '—'],
            ['label' => 'Source',         'value' => $lead->source ?: ($lead->lead_source ? (\App\Models\Lead::LEAD_SOURCES[$lead->lead_source] ?? $lead->lead_source) : '—')],
            ['label' => 'Created',        'value' => $lead->created_at->format('d M Y, g:i A')],
            ['label' => 'Last Updated',   'value' => $lead->updated_at->format('d M Y, g:i A')],
        ];
    @endphp
    @foreach($fields as $i => $field)
    <div style="padding:14px 24px;border-bottom:1px solid #f9fafb;{{ $i % 2 === 0 ? 'border-right:1px solid #f9fafb' : '' }}">
        <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">{{ $field['label'] }}</div>
        <div style="font-size:14px;color:#1f2937;font-weight:500">{{ $field['value'] }}</div>
    </div>
    @endforeach
</div>

{{-- Original note — captured when the lead was created/edited. --}}
@if($lead->notes)
<div style="padding:20px 24px;border-top:1px solid #f3f4f6">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Original Note</div>
    <p style="font-size:14px;color:#374151;line-height:1.6;margin:0">{{ $lead->notes }}</p>
</div>
@endif

{{-- Activity Log — every "+ Activity" entry logged on this lead, newest
     first, with who logged it and when (LeadActivity::$by / activity_date /
     activity_time). This is the piece that was missing: logActivity() always
     saved this, the board just never displayed it back. --}}
<div style="padding:20px 24px;border-top:1px solid #f3f4f6" id="ppActivityLogSection">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Activity Log</div>

    @forelse($lead->activities as $activity)
    <div style="display:flex;gap:10px;margin-bottom:12px;">
        <span style="font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;height:fit-content;background:#f0eefc;color:#534AB7;">
            {{ $activity->label ?: ucfirst(str_replace('_', ' ', $activity->type)) }}
        </span>
        <div style="flex:1;min-width:0;">
            @if($activity->note)
            <p style="font-size:13.5px;color:#374151;line-height:1.5;margin:0;">{{ $activity->note }}</p>
            @endif
            @if($activity->outcome)
            <p style="font-size:12px;color:#6b7280;margin:2px 0 0;">Outcome: {{ ucfirst(str_replace('_', ' ', $activity->outcome)) }}</p>
            @endif
            <div style="font-size:11px;color:#9ca3af;margin-top:3px;">
                {{ $activity->by ?: 'Staff' }} · {{ $activity->date }}
            </div>
        </div>
    </div>
    @empty
    <p style="font-size:13px;color:#9ca3af;margin:0 0 12px;">No activity logged yet.</p>
    @endforelse

    {{-- Log Activity form — same fields as the old bare modal, now inline
         with the visible history above it. Posts to the existing
         relationship.pipeline.activity endpoint, then reloads this modal. --}}
    <div x-data="{ actType: 'note', actNote: '', saving: false, error: '' }" style="margin-top:8px;">
        <div style="display:flex;gap:8px;align-items:flex-start;">
            <select x-model="actType" style="padding:8px;border:1px solid #e5e7eb;border-radius:8px;font-size:12.5px;flex-shrink:0;">
                <option value="note">Note</option>
                <option value="suggestion">Suggestion (staff observation)</option>
                <option value="response">Patient Response</option>
                <option value="call">Call</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">SMS</option>
                <option value="email">Email</option>
            </select>
            <textarea x-model="actNote" rows="2" placeholder="What happened?"
                      style="flex:1;padding:8px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
        </div>
        <template x-if="error">
            <div style="font-size:12px;color:#b52020;margin-top:6px;" x-text="error"></div>
        </template>
        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
            <button type="button"
                    :disabled="!actNote.trim() || saving"
                    @click="
                        saving = true; error = '';
                        const labelMap = { note: 'Note added', suggestion: 'Suggestion added', response: 'Patient response logged', call: 'Call logged', whatsapp: 'WhatsApp sent', sms: 'SMS sent', email: 'Email sent' };
                        fetch('{{ route('relationship.pipeline.activity', $lead->id) }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            body: JSON.stringify({ type: actType, label: labelMap[actType] || 'Note added', note: actNote.trim() }),
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) { ppOpenDetail({{ $lead->id }}); }
                            else { error = data.message || 'Could not save this activity.'; saving = false; }
                        })
                        .catch(() => { error = 'Network error. Please try again.'; saving = false; })
                    "
                    style="padding:7px 16px;border:none;border-radius:8px;background:#534AB7;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;">
                <span x-show="!saving">Add Activity</span>
                <span x-show="saving">Saving…</span>
            </button>
        </div>
    </div>
</div>
