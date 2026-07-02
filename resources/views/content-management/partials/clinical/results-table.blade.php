{{-- Results Table --}}
@if(isset($results) && $results->count() > 0)

<div style="border:1px solid #f3f4f6;border-radius:6px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Patient</th>
                <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Treatment</th>
                <th style="padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Tooth</th>
                <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Start Date</th>
                <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Completion</th>
                <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Last Follow-up</th>
                <th style="padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Media</th>
                <th style="padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Status</th>
                <th style="padding:10px 14px;width:32px;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $row)
            <tr class="cms-result-row"
                onclick="openCaseViewer({{ $row->id }})"
                style="border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .1s;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='white'">

                {{-- Patient --}}
                <td style="padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700;flex-shrink:0;">
                            {{ strtoupper(substr($row->patient->name ?? 'P', 0, 2)) }}
                        </div>
                        <div>
                            <div style="font-weight:600;color:#111827;">{{ $row->patient->name ?? '—' }}</div>
                            <div style="font-size:11px;color:#9ca3af;">
                                {{ $row->patient->date_of_birth ? \Carbon\Carbon::parse($row->patient->date_of_birth)->age . 'Y' : '' }}
                                {{ $row->patient->gender ? ' / ' . ucfirst($row->patient->gender) : '' }}
                            </div>
                        </div>
                    </div>
                </td>

                {{-- Treatment --}}
                <td style="padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        @include('content-management.partials.shared.media-type-badge', ['type' => $row->media_type ?? 'photo'])
                        <span style="font-weight:500;color:#374151;">{{ $row->treatment_name ?? '—' }}</span>
                    </div>
                </td>

                {{-- Tooth --}}
                <td style="padding:12px 14px;text-align:center;">
                    @if($row->tooth_no)
                    <span style="display:inline-block;padding:2px 8px;background:#f3f4f6;border-radius:4px;font-size:11px;font-weight:700;color:#374151;">
                        {{ $row->tooth_no }}
                    </span>
                    @else
                    <span style="color:#d1d5db;">—</span>
                    @endif
                </td>

                {{-- Start Date --}}
                <td style="padding:12px 14px;font-size:12px;color:#6b7280;">
                    {{ $row->treatment_start_date ? $row->treatment_start_date->format('d M Y') : '—' }}
                </td>

                {{-- Completion --}}
                <td style="padding:12px 14px;font-size:12px;color:#6b7280;">
                    {{ $row->treatment_end_date ? $row->treatment_end_date->format('d M Y') : '—' }}
                </td>

                {{-- Last Follow-up --}}
                <td style="padding:12px 14px;font-size:12px;color:#6b7280;">—</td>

                {{-- Media Count --}}
                <td style="padding:12px 14px;text-align:center;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:4px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span style="font-size:12px;font-weight:600;color:#374151;">
                            {{ CmsMedia::where('patient_id', $row->patient_id)->where('treatment_name', $row->treatment_name)->count() }}
                        </span>
                    </div>
                </td>

                {{-- Status --}}
                <td style="padding:12px 14px;text-align:center;">
                    @include('content-management.partials.shared.tag-pill', [
                        'label'  => ucfirst($row->treatment_status ?? 'ongoing'),
                        'color'  => match($row->treatment_status) {
                            'completed' => '#16a34a',
                            'paused'    => '#d97706',
                            default     => '#2563eb',
                        },
                        'bg'     => match($row->treatment_status) {
                            'completed' => '#dcfce7',
                            'paused'    => '#fff7ed',
                            default     => '#dbeafe',
                        },
                    ])
                </td>

                {{-- Actions --}}
                <td style="padding:12px 8px;" onclick="event.stopPropagation()">
                    <button type="button"
                            onclick="cmsRowMenu(event, {{ $row->id }})"
                            style="background:none;border:1px solid #e5e7eb;border-radius:4px;padding:4px 6px;cursor:pointer;color:#6b7280;"
                            onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    </button>
                </td>

            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Pagination --}}
@if($results->hasPages())
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding:0 2px;">
    <span style="font-size:12px;color:#9ca3af;">
        Showing {{ $results->firstItem() }}–{{ $results->lastItem() }} of {{ $results->total() }} results
    </span>
    <div style="display:flex;align-items:center;gap:4px;">
        @if($results->onFirstPage())
            <span class="cms-page-btn" style="opacity:.4;cursor:not-allowed;">‹</span>
        @else
            <button class="cms-page-btn" onclick="window.cmsSearch?.goPage({{ $results->currentPage() - 1 }})">‹</button>
        @endif

        @foreach($results->getUrlRange(max(1, $results->currentPage()-2), min($results->lastPage(), $results->currentPage()+2)) as $page => $url)
            <button class="cms-page-btn {{ $page == $results->currentPage() ? 'cms-page-active' : '' }}"
                    onclick="window.cmsSearch?.goPage({{ $page }})">{{ $page }}</button>
        @endforeach

        @if($results->hasMorePages())
            <button class="cms-page-btn" onclick="window.cmsSearch?.goPage({{ $results->currentPage() + 1 }})">›</button>
        @else
            <span class="cms-page-btn" style="opacity:.4;cursor:not-allowed;">›</span>
        @endif
    </div>
</div>
@endif

@elseif(isset($results))

{{-- Empty state --}}
<div style="text-align:center;padding:60px 20px;">
    <div style="font-size:15px;font-weight:600;color:#374151;margin-bottom:4px;">No records found</div>
    <div style="font-size:13px;color:#9ca3af;">Try adjusting your filters or search terms</div>
    <button type="button" onclick="window.cmsSearch?.reset()"
            style="margin-top:14px;padding:8px 20px;border:1px solid #e5e7eb;border-radius:5px;background:white;font-size:12px;font-weight:600;color:#374151;cursor:pointer;">
        Clear Filters
    </button>
</div>

@else

{{-- Initial load state --}}
<div style="text-align:center;padding:60px 20px;">
    <div style="font-size:13px;color:#9ca3af;">Use the filters above to search clinical records</div>
</div>

@endif

@php
use App\Models\CmsMedia;
@endphp
