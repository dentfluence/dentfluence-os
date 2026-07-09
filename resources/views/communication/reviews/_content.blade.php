{{--
    Shared Reviews & Reputation content — extracted 2026-07-09 so the same
    board can render natively inside Marketing (resources/views/marketing/reviews/index.blade.php)
    without linking out to Communication's URL. The underlying data/actions
    (ReviewService, Review model, send/reply routes) still live in
    Communication — only this markup is shared, not duplicated.

    Filter tab links use the CURRENT route name so they stay on whichever
    page included this partial (communication.reviews.index or
    marketing.reviews) instead of always jumping to Communication's URL.
--}}
@php $threshold = (int) config('reviews.positive_threshold', 4); @endphp
<div style="padding:20px 24px; max-width:1000px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px;">
        <div>
            <h1 style="font-size:20px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                <i class="ti ti-star" style="color:#FBBF24;"></i> Reviews &amp; Reputation
            </h1>
            <p style="margin:4px 0 0; color:#6b7280; font-size:13px;">Ask happy patients for Google reviews; catch unhappy feedback privately.</p>
        </div>
        @if(config('whatsapp.dry_run'))
            <span style="background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; white-space:nowrap;">DRY-RUN MODE</span>
        @endif
    </div>

    @if(session('success'))
        <div style="background:#DCFCE7; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#FEE2E2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif

    {{-- Stat cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:18px;">
        @php
            $cards = [
                ['Requests sent', $stats['requested'], '#6b7280'],
                ['Responses', $stats['rated'], '#2563eb'],
                ['Avg rating', $stats['avg'] ? $stats['avg'].' ★' : '—', '#d97706'],
                ['Happy ('.$threshold.'★+)', $stats['positive'], '#16a34a'],
                ['Needs attention', $stats['negative'], '#dc2626'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color])
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px;">
                <div style="font-size:22px; font-weight:700; color:{{ $color }};">{{ $value }}</div>
                <div style="font-size:12px; color:#6b7280; margin-top:2px;">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- Send a request — search by name/phone instead of a raw Patient ID
         (staff don't memorize numeric IDs; matches the same /patients/search
         lookup used by the appointment-booking modal). --}}
    <form method="POST" action="{{ route('communication.reviews.send') }}"
          x-data="{
              query: '', results: [], loading: false, selected: null, searchTimer: null,
              search() {
                  clearTimeout(this.searchTimer);
                  if (this.query.length < 2) { this.results = []; return; }
                  this.searchTimer = setTimeout(async () => {
                      this.loading = true;
                      try {
                          const resp = await fetch('{{ route('patients.search') }}?q=' + encodeURIComponent(this.query), {
                              headers: { 'Accept': 'application/json' }
                          });
                          this.results = resp.ok ? await resp.json() : [];
                      } finally { this.loading = false; }
                  }, 300);
              },
              select(p) { this.selected = p; this.query = p.name; this.results = []; },
              clear() { this.selected = null; this.query = ''; this.results = []; }
          }"
          style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-bottom:18px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        @csrf
        <div style="flex:1 1 260px; position:relative;">
            <label style="display:block; font-size:12px; color:#6b7280; margin-bottom:4px;">Patient</label>

            <input x-show="!selected" type="text" x-model="query" @input="search()" autocomplete="off"
                   placeholder="Search patient by name or phone…"
                   style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:9px 11px; font-size:14px; box-sizing:border-box;">

            <div x-show="selected" style="display:flex; align-items:center; justify-content:space-between; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; padding:8px 11px; font-size:14px;">
                <span x-text="selected && selected.name"></span>
                <button type="button" @click="clear()" style="background:none; border:none; color:#6b7280; cursor:pointer; font-size:15px; line-height:1; padding:2px;" title="Change patient">&times;</button>
            </div>

            <div x-show="results.length > 0" x-cloak @click.outside="results = []"
                 style="position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; margin-top:4px; max-height:220px; overflow-y:auto; z-index:20; box-shadow:0 4px 12px rgba(0,0,0,.08);">
                <template x-for="p in results" :key="p.id">
                    <button type="button" @click="select(p)"
                            style="display:block; width:100%; text-align:left; padding:8px 11px; font-size:13px; border:none; border-bottom:1px solid #f1f5f9; background:#fff; cursor:pointer;"
                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">
                        <span x-text="p.name" style="color:#111827;"></span>
                        <span x-text="p.meta ? ' · ' + p.meta : ''" style="color:#9ca3af; font-size:11px;"></span>
                    </button>
                </template>
            </div>

            <p x-show="loading" style="font-size:11px; color:#9ca3af; margin:4px 0 0;">Searching…</p>
            <input type="hidden" name="patient_id" :value="selected ? selected.id : ''">
        </div>
        <button type="submit" :disabled="!selected"
                :style="!selected ? 'background:#9ca3af; color:#fff; border:none; border-radius:9px; padding:10px 16px; font-size:14px; font-weight:600; cursor:not-allowed; display:inline-flex; gap:6px; align-items:center;' : 'background:#0F6E56; color:#fff; border:none; border-radius:9px; padding:10px 16px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; gap:6px; align-items:center;'">
            <i class="ti ti-send"></i> Send review request
        </button>
        <span style="font-size:11px; color:#9ca3af;">Requires the patient's WhatsApp consent (DPDP).</span>
    </form>

    {{-- Filter tabs --}}
    @php
        $filters = ['all'=>'All', 'pending'=>'Pending', 'positive'=>'Happy', 'negative'=>'Needs attention'];
        $currentRouteName = request()->route()->getName();
    @endphp
    <div style="display:flex; gap:6px; margin-bottom:10px;">
        @foreach($filters as $key => $label)
            <a href="{{ route($currentRouteName, ['filter' => $key]) }}"
               style="font-size:13px; padding:6px 12px; border-radius:8px; text-decoration:none; {{ $filter === $key ? 'background:#0F6E56;color:#fff;' : 'background:#fff;color:#374151;border:1px solid #e5e7eb;' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Reviews table --}}
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
            <thead>
                <tr style="background:#f8fafc; text-align:left; color:#6b7280; font-size:12px;">
                    <th style="padding:10px 14px;">Patient</th>
                    <th style="padding:10px 14px;">Rating</th>
                    <th style="padding:10px 14px;">Comment</th>
                    <th style="padding:10px 14px;">Status</th>
                    <th style="padding:10px 14px;">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $r)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:10px 14px;">{{ $r->patient->name ?? '—' }}</td>
                        <td style="padding:10px 14px; white-space:nowrap;">
                            @if($r->rating)
                                <span style="color:#FBBF24;">{!! str_repeat('&#9733;', $r->rating) !!}</span><span style="color:#e5e7eb;">{!! str_repeat('&#9733;', 5 - $r->rating) !!}</span>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        <td style="padding:10px 14px; color:#4b5563; max-width:320px;">
                            {{ $r->comment ?: '—' }}

                            @if ($r->clinic_reply)
                                <div style="margin-top:6px; padding:8px 10px; background:#f8fafc; border-radius:6px; font-size:12px; color:#374151;">
                                    <strong style="color:#6b7280;">Reply:</strong> {{ $r->clinic_reply }}
                                    <div style="color:#9ca3af; font-size:11px; margin-top:2px;">{{ $r->repliedBy->name ?? 'Staff' }} · {{ optional($r->replied_at)->format('d M, h:i A') }}</div>
                                </div>
                            @elseif ($r->needsReply())
                                <details style="margin-top:6px;">
                                    <summary style="cursor:pointer; font-size:12px; color:#0F6E56; font-weight:600;">Reply (internal note)</summary>
                                    <form method="POST" action="{{ route('communication.reviews.reply', $r) }}" style="margin-top:6px; display:flex; gap:6px; align-items:flex-start;">
                                        @csrf
                                        <textarea name="clinic_reply" required rows="2" placeholder="e.g. Called patient, resolved billing concern."
                                            style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12px; resize:vertical;"></textarea>
                                        <button type="submit" style="background:#0F6E56; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">Save</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td style="padding:10px 14px;">
                            @if($r->status === 'rated')
                                @if($r->isPositive())
                                    <span style="background:#DCFCE7; color:#166534; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px;">{{ $r->routed_to_google ? 'Sent to Google' : 'Happy' }}</span>
                                @else
                                    <span style="background:#FEE2E2; color:#991B1B; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px;">Needs attention</span>
                                @endif
                            @else
                                <span style="background:#F3F4F6; color:#6b7280; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px;">Pending</span>
                            @endif
                        </td>
                        <td style="padding:10px 14px; color:#9ca3af; white-space:nowrap;">{{ optional($r->responded_at ?? $r->requested_at)->format('d M, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:40px 14px; text-align:center; color:#9ca3af;">No reviews yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px;">{{ $reviews->links() }}</div>
</div>
