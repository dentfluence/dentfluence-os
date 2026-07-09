{{-- Clinical Filters Bar --}}
<div id="cms-filters" style="background:#faf5fb;border:1px solid rgba(106,15,112,.1);border-radius:8px;padding:16px;margin-bottom:14px;">

    {{-- Row 1: Main filters --}}
    <div style="display:grid;grid-template-columns:1fr 160px 1fr 130px 130px;gap:10px;margin-bottom:10px;align-items:end;">

        {{-- Global Search --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Search</label>
            <div style="position:relative;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" id="cms-q" placeholder="Patient name, treatment, tooth…"
                       style="width:100%;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px 7px 32px;font-size:13px;color:#111827;background:white;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#e5e7eb'"
                       oninput="window.cmsSearch?.debounceSearch()">
            </div>
        </div>

        {{-- Tooth Number --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Tooth No.</label>
            <input type="text" id="cms-tooth" placeholder="e.g. 46"
                   style="width:100%;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px;font-size:13px;color:#111827;background:white;outline:none;transition:border-color .15s;"
                   onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#e5e7eb'"
                   oninput="window.cmsSearch?.debounceSearch()">
        </div>

        {{-- Treatment --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Treatment</label>
            <select id="cms-treatment"
                    style="width:100%;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px;font-size:13px;color:#111827;background:white;outline:none;cursor:pointer;"
                    onchange="window.cmsSearch?.debounceSearch()">
                <option value="">All Treatments</option>
                @foreach(($treatmentCategoryOptions ?? \App\Models\ClinicalFile::TREATMENT_CATEGORIES) as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <label style="display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Status</label>
            <select id="cms-status"
                    style="width:100%;border:1px solid #e5e7eb;border-radius:5px;padding:7px 10px;font-size:13px;color:#111827;background:white;outline:none;cursor:pointer;"
                    onchange="window.cmsSearch?.debounceSearch()">
                <option value="">All Statuses</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="paused">Paused</option>
            </select>
        </div>

        {{-- Search + Reset buttons --}}
        <div style="display:flex;gap:6px;">
            <button type="button" onclick="window.cmsSearch?.doSearch()"
                    style="flex:1;padding:7px 0;background:#6a0f70;color:white;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;"
                    onmouseover="this.style.background='#380740'" onmouseout="this.style.background='#6a0f70'">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Search
            </button>
            <button type="button" onclick="window.cmsSearch?.reset()"
                    style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:5px;background:white;font-size:12px;font-weight:600;color:#6b7280;cursor:pointer;"
                    onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280'">
                Reset
            </button>
        </div>

    </div>

    {{-- Row 2: Secondary filters (expandable) --}}
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">

        {{-- Patient dropdown --}}
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:11px;color:#6b7280;font-weight:600;white-space:nowrap;">Patient:</label>
            <select id="cms-patient"
                    style="border:1px solid #e5e7eb;border-radius:4px;padding:4px 8px;font-size:12px;color:#374151;background:white;cursor:pointer;outline:none;min-width:140px;"
                    onchange="window.cmsSearch?.debounceSearch()">
                <option value="">All Patients</option>
                @if(isset($patients))
                    @foreach($patients as $p)
                    <option value="{{ $p->id }}" {{ (isset($prefilterPatient) && $prefilterPatient->id === $p->id) ? 'selected' : '' }}>
                        {{ $p->name }}
                    </option>
                    @endforeach
                @endif
            </select>
        </div>

        {{-- Date range --}}
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:11px;color:#6b7280;font-weight:600;white-space:nowrap;">From:</label>
            <input type="date" id="cms-date-from"
                   style="border:1px solid #e5e7eb;border-radius:4px;padding:4px 8px;font-size:12px;color:#374151;background:white;outline:none;"
                   onchange="window.cmsSearch?.debounceSearch()">
            <label style="font-size:11px;color:#6b7280;font-weight:600;">To:</label>
            <input type="date" id="cms-date-to"
                   style="border:1px solid #e5e7eb;border-radius:4px;padding:4px 8px;font-size:12px;color:#374151;background:white;outline:none;"
                   onchange="window.cmsSearch?.debounceSearch()">
        </div>

        {{-- Rows per page --}}
        <div style="display:flex;align-items:center;gap:5px;margin-left:auto;">
            <label style="font-size:11px;color:#9ca3af;">Rows:</label>
            <select id="cms-per-page" onchange="window.cmsSearch?.doSearch()"
                    style="border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;font-size:11px;color:#374151;background:white;cursor:pointer;outline:none;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>

    </div>
</div>
