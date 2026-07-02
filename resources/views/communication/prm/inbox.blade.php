@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Things to Do — PRM')

@section('communication-content')

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;">
    <a href="{{ route('prm.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Pipeline
    </a>
</div>

<div style="padding:28px 28px 60px;max-width:1100px;">

    {{-- Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">
            Things to Do
        </h1>
        <p style="color:#9a7aaa;font-size:13px;margin:0;">
            New leads to action and follow-ups that are due — your one place to start the day.
        </p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

        {{-- ── NEW LEADS ──────────────────────────────────────────────── --}}
        <div style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:600;color:#1a0320;"><i class="ti ti-inbox" style="color:#534AB7;"></i> New Leads to Action</span>
                <span style="font-size:12px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:20px;padding:2px 9px;">{{ count($newLeads) }}</span>
            </div>
            @forelse($newLeads as $lead)
                <a href="/communication/prm/lead/{{ $lead->id }}" style="display:block;padding:12px 16px;border-bottom:1px solid #f6f6f6;text-decoration:none;color:inherit;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="font-weight:600;color:#1a0320;">{{ $lead->name }}</div>
                        <div style="font-size:11px;color:#9a7aaa;">{{ $lead->created_at?->diffForHumans() }}</div>
                    </div>
                    <div style="font-size:12px;color:#5A5A56;margin-top:2px;">{{ $lead->phone }}</div>
                    @if($lead->ai_summary)
                        <div style="font-size:11px;color:#7A5AF8;margin-top:4px;"><i class="ti ti-sparkles"></i> {{ $lead->ai_summary }}</div>
                    @endif
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                        @if($lead->ai_treatment_label ?: $lead->treatment)
                            <span style="font-size:10px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:20px;padding:2px 7px;">{{ $lead->ai_treatment_label ?: $lead->treatment }}</span>
                        @endif
                        <span style="font-size:10px;color:#9a7aaa;">via {{ $lead->source ?: '—' }}</span>
                        @if($lead->assigned_to)
                            <span style="font-size:10px;color:#9a7aaa;">· {{ $lead->assigned_to }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div style="padding:24px;text-align:center;color:#bbb;font-size:13px;">No new leads — you're all caught up. 🎉</div>
            @endforelse
        </div>

        {{-- ── DUE FOLLOW-UPS ─────────────────────────────────────────── --}}
        <div style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:600;color:#1a0320;"><i class="ti ti-bell" style="color:#854F0B;"></i> Follow-ups Due / Overdue</span>
                <span style="font-size:12px;font-weight:600;color:#854F0B;background:#FAEEDA;border-radius:20px;padding:2px 9px;">{{ count($dueFollowups) }}</span>
            </div>
            @forelse($dueFollowups as $fu)
                @php $isOverdue = $fu->due_date->isPast() && ! $fu->due_date->isToday(); @endphp
                <a href="{{ $fu->lead_id ? '/communication/prm/lead/' . $fu->lead_id : '#' }}" style="display:block;padding:12px 16px;border-bottom:1px solid #f6f6f6;text-decoration:none;color:inherit;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="font-weight:600;color:#1a0320;">{{ $fu->subjectName() }}</div>
                        @if($isOverdue)
                            <span style="font-size:10px;font-weight:600;color:#E24B4A;background:#FBE9E9;border-radius:20px;padding:2px 8px;">Overdue</span>
                        @else
                            <span style="font-size:10px;font-weight:600;color:#854F0B;background:#FAEEDA;border-radius:20px;padding:2px 8px;">Due today</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:#5A5A56;margin-top:2px;">{{ $fu->label }}</div>
                    <div style="font-size:11px;color:#9a7aaa;margin-top:4px;">
                        <i class="ti ti-calendar"></i> {{ $fu->due_date->format('d M Y') }}
                        · <i class="ti ti-{{ $fu->channel === 'whatsapp' ? 'brand-whatsapp' : 'phone' }}"></i> {{ ucfirst($fu->channel) }}
                    </div>
                </a>
            @empty
                <div style="padding:24px;text-align:center;color:#bbb;font-size:13px;">No follow-ups due. Nicely done.</div>
            @endforelse
        </div>

    </div>
</div>

@endsection
