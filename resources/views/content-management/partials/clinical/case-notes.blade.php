{{-- Case Notes --}}
<div style="padding:4px 0;">
    <div style="font-size:12px;color:#9ca3af;margin-bottom:16px;">
        Notes are pulled from the original consultation and visit records.
    </div>

    @php
        // Try to load consultation notes if consultation_id exists
        $consultation = null;
        if(isset($media) && $media->consultation_id) {
            $consultation = \App\Models\Consultation::find($media->consultation_id);
        }
    @endphp

    @if($consultation)
    <div style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:8px;padding:14px;margin-bottom:12px;">
        <div style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Consultation Notes</div>
        @if($consultation->chief_complaint)
        <div style="margin-bottom:8px;">
            <div style="font-size:10px;color:#9ca3af;font-weight:600;margin-bottom:2px;">Chief Complaint</div>
            <div style="font-size:13px;color:#374151;">{{ $consultation->chief_complaint }}</div>
        </div>
        @endif
        @if($consultation->primary_diagnosis ?? false)
        <div style="margin-bottom:8px;">
            <div style="font-size:10px;color:#9ca3af;font-weight:600;margin-bottom:2px;">Diagnosis</div>
            <div style="font-size:13px;color:#374151;">{{ $consultation->primary_diagnosis }}</div>
        </div>
        @endif
    </div>
    @else
    <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e5e7eb" stroke-width="1.5" style="margin:0 auto 10px;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <div style="font-size:13px;">No notes linked to this case</div>
        <div style="font-size:11px;margin-top:4px;">Notes are attached via the Consultation module</div>
    </div>
    @endif
</div>
