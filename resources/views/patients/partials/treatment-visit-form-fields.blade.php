        {{-- Body — true two-column clinical workspace (08-05 layout sprint).
             Left (~70%) is the doctor's active work surface: Patient Brief,
             Medical Alerts, Vitals, Clinical Notes (hero), Today's Work,
             Procedure Worksheets. Right (~30%) is the read-only "what
             Dentfluence will do" panel: Treatment Progress, Lab Preview,
             Billing Preview, Next Visit, Summary — all system-derived,
             visually secondary. Zero Alpine/behaviour changes: every
             x-model/x-show/x-data/dusk selector below is the exact same
             one from the previous single-column layout, only relocated
             and restyled. --}}

        {{-- Moved here (08-05 polish sprint) from treatment-visits-tab.blade.php:
             this partial is the one file BOTH the tab fragment and the
             dedicated visits.create/visits.edit page include, so the styling
             now loads on both instead of being silently missing on the page.
             UX-06: .tv-rx-* styles removed with the dead embedded Rx entry UI. --}}
        <style>
            .tv-stage-btn { padding:4px 10px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;border-radius:99px;cursor:pointer;transition:all .15s;background:white;color:#6b7280; }
            .tv-stage-btn:hover { border-color:#6a0f70;color:#6a0f70; }
            .tv-stage-btn.done { background:#16a34a;border-color:#15803d;color:white; }
            .tv-stage-btn.current { background:#6a0f70;border-color:#380740;color:white; }
            .tv-section-legend { font-size:10.5px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
            .tv-section-legend::after { content:'';flex:1;height:1px;background:#f3f4f6; }

            /* Information-Architecture polish (visual zoning only) — page-level
               "chapter" headings, deliberately distinct from .tv-section-legend
               (card titles): larger, neutral gray-800 instead of brand purple,
               no inline fill-line. A full-width light divider sits below it as
               its own element, with generous margin on both sides, so the eye
               reads four chapters (Patient Context / Today's Encounter /
               Procedure Worksheet / Complete Visit) without needing borders or
               new colors anywhere in the page body. */
            .tv-zone-heading { font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px; }
            .tv-zone-divider { border-bottom:1px solid #e5e7eb;margin-bottom:28px; }

            /* Sticky footer (08-05 follow-up): position:sticky wasn't holding
               against #df-content-area's scroll in this app shell — no other
               page in the app has a sticky BOTTOM element to have proven the
               pattern (only sticky top-* is used elsewhere), so this was
               untested territory. Switched to position:fixed, anchored to
               the right edge and offset from the left by the sidebar's own
               current width. Mirrors layouts/app.blade.php's own sidebar
               width rules exactly (240px expanded / 64px collapsed or
               tablet / 0 on mobile drawer) via the same #df-shell
               data-sidebar attribute and breakpoints the layout already
               uses — reads that existing state, doesn't modify the shared
               layout file. */
            .tv-sticky-footer {
                position: fixed;
                bottom: 0;
                left: 240px;
                right: 0;
                z-index: 40;
            }
            #df-shell[data-sidebar="collapsed"] .tv-sticky-footer { left: 64px; }
            @media (min-width: 768px) and (max-width: 1199px) {
                .tv-sticky-footer { left: 64px; }
            }
            @media (max-width: 767px) {
                .tv-sticky-footer { left: 0; }
            }
        </style>

        {{-- pb-24 (08-05 footer fix): the footer switched from sticky to
             fixed positioning, so it no longer reserves its own space in
             normal flow — this bottom padding keeps the last card (Crown
             Prep worksheet / Intelligence Panel) from being covered by it. --}}
        <div class="max-w-[1440px] mx-auto px-6 pt-6 pb-24">

            {{-- Blocking banners — full width, above the workspace split --}}

                {{-- Error --}}
                <div x-show="errorMsg" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span x-text="errorMsg"></span>
                </div>

                {{-- Repeat-work warning: same treatment + tooth already done before --}}
                <div x-show="repeatWarnings.length > 0" x-cloak class="px-4 py-3 bg-amber-50 border border-amber-300 rounded-lg">
                    <div class="flex items-start gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-shrink-0"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-amber-800">This may be repeat work</p>
                            <ul class="mt-1 space-y-0.5">
                                <template x-for="w in repeatWarnings" :key="w.treatment_name + '|' + w.tooth">
                                    <li class="text-xs text-amber-700">
                                        <span class="font-semibold" x-text="w.treatment_name"></span>
                                        on <span class="font-semibold" x-text="'Tooth ' + w.tooth"></span>
                                        was already done on <span class="font-semibold" x-text="_fmtDate(w.date)"></span>.
                                    </li>
                                </template>
                            </ul>
                            <label class="block text-xs font-semibold text-amber-800 mt-2 mb-1">
                                Reason for repeat work <span class="text-red-500">*</span>
                            </label>
                            <textarea x-model="repeatReason" rows="2"
                                      placeholder="e.g. Filling dislodged / recurrent decay / patient discomfort"
                                      class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"></textarea>
                            <p class="text-[11px] text-amber-600 mt-1">Required — this visit is tracked as repeat work for reporting.</p>
                        </div>
                    </div>
                </div>

            {{-- 40/30/30 restructure (08-05 follow-up). Dashboard row
                 above this point is UNCHANGED -- same markup, same classes,
                 same nesting depth as the previous 70/30 layout; it now
                 renders full-width instead of confined to a 70% sub-column,
                 which is the one unavoidable visual side-effect of removing
                 the old two-column wrapper (flagged in the implementation
                 report; zero characters inside Patient Brief / Medical
                 Alerts / Vitals themselves changed). --}}
            <div class="space-y-6">

                {{-- IA polish (visual zoning only) — Chapter 1 of 4. Groups
                     the existing, unmodified dashboard row (Visit Information/
                     Patient Brief/Medical Alerts/Vitals) under one page-level
                     heading so it reads as "before treatment begins" instead
                     of blending into Today's Encounter below it. Heading +
                     divider only — no card inside this section changed. --}}
                <div>
                    <div class="tv-zone-heading">Patient Context</div>
                    <div class="tv-zone-divider"></div>
                </div>

                <div class="space-y-5 min-w-0">

                    {{-- ══ TOP DASHBOARD ROW — compact 4-card row (08-05
                         follow-up): Visit Information / Patient Brief /
                         Medical Alerts / Vitals as four equal-height,
                         equal-padding cards so the clinical workspace
                         begins one row sooner instead of two. Desktop
                         4-col, tablet 2-col, mobile stacked — CSS Grid's
                         default row-stretch keeps every card the same
                         height regardless of collapsed/expanded state or
                         content length, no manual height forcing. Every
                         value below is EXISTING bound state —
                         $patient->age/->gender, $_clinicalAlerts,
                         zone0LastLine/zone0PlanLines/form.* vitals fields,
                         form.visit_date/doctor_id/appointment_id/visit_type
                         (all existing Alpine getters/x-models), $doctorsList/
                         auth() user (existing Blade). No new bindings, no
                         new queries, no new state — presentation only.
                         Everything from Clinical Notes down is untouched. --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    {{-- Card 1 — Visit Information. Restyled from the old
                         standalone toolbar strip into a card matching the
                         other three exactly (bg-white, border, rounded-xl,
                         shadow-sm, px-5 py-4) so no card reads as visually
                         heavier. Same four fields, same bindings, same
                         onAppointmentChange() — no popup, no "Edit" button,
                         still directly editable inline. --}}
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-5 py-4">
                        <span class="text-[10px] font-bold uppercase tracking-[.07em] text-[#6a0f70] flex items-center gap-2">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Visit Information
                        </span>
                        <div class="mt-3 space-y-2.5">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 block mb-0.5">Visit Date</label>
                                <input type="date" x-model="form.visit_date"
                                       class="w-full text-xs font-semibold border-0 bg-transparent focus:outline-none focus:ring-0 text-gray-800 p-0">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 block mb-0.5">Treating Doctor</label>
                                <select x-model="form.doctor_id"
                                        class="w-full text-xs font-semibold border-0 bg-transparent focus:outline-none focus:ring-0 text-gray-800 p-0">
                                    @forelse($doctorsList as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                                    @empty
                                        <option value="{{ auth()->id() }}">{{ auth()->user()->name }}</option>
                                    @endforelse
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 block mb-0.5">Visit Type</label>
                                <select x-model="form.visit_type"
                                        class="w-full text-xs font-semibold border-0 bg-transparent focus:outline-none focus:ring-0 text-[#6a0f70] p-0 capitalize">
                                    <option value="treatment">Treatment</option>
                                    <option value="followup">Follow-up</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="recall">Recall</option>
                                </select>
                            </div>
                            <div x-show="TV_APPOINTMENTS.length > 0">
                                <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 block mb-0.5">Appointment</label>
                                <select x-model="form.appointment_id" @change="onAppointmentChange()"
                                        class="w-full text-xs font-semibold border-0 bg-transparent focus:outline-none focus:ring-0 text-gray-800 p-0">
                                    <option value="">— None linked —</option>
                                    <template x-for="appt in TV_APPOINTMENTS" :key="appt.id">
                                        <option :value="appt.id" x-text="appt.label"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>{{-- /Card 1 — Visit Information --}}

                    {{-- Card 2 — Patient Brief. Collapsible (08-05 follow-up):
                         briefOpen is a LOCAL nested x-data scope — it does not
                         touch the shared treatmentVisits() factory, the payload,
                         or any existing binding. Collapsing only hides the
                         detail block; the name header stays visible and a
                         one-line summary appears in its place when closed. --}}
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" x-data="{ briefOpen: true }">
                        <div class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <h2 class="text-base font-bold text-gray-900">{{ $patient->name }}</h2>
                                        @if($patient->age || $patient->gender)
                                        <span class="text-xs text-gray-500 font-medium">
                                            {{ collect([$patient->age, $patient->gender ? ucfirst($patient->gender) : null])->filter()->implode(' · ') }}
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button type="button" @click="briefOpen = !briefOpen"
                                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-md p-2.5 -m-1 transition-colors"
                                            :aria-label="briefOpen ? 'Collapse patient brief' : 'Expand patient brief'">
                                        <svg :class="briefOpen ? 'rotate-180' : ''" class="transition-transform" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Collapsed-state one-line summary --}}
                            <p x-show="!briefOpen" x-cloak class="text-xs text-gray-500 mt-2 truncate" x-text="zone0LastLine || 'No previous visit recorded'"></p>

                            <div x-show="briefOpen" x-collapse>
                            {{-- Foregrounded: Last Visit / Last Treatment / Current Plan
                                 Progress / Assigned Doctor — the four facts this card
                                 must answer per spec, all from existing getters. --}}
                            <div class="mt-3 space-y-1.5">
                                <p class="text-xs text-gray-600 flex items-start gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 flex-shrink-0 mt-1"></span>
                                    <span x-text="zone0LastLine || 'No previous visit recorded'"></span>
                                </p>
                                <template x-if="zone0PlanLines.length === 0">
                                    <p class="text-xs text-gray-500 flex items-start gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-200 flex-shrink-0 mt-1"></span>
                                        <span>No active treatment plan</span>
                                    </p>
                                </template>
                                <template x-for="line in zone0PlanLines" :key="line">
                                    <p class="text-xs text-gray-600 flex items-start gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#6a0f70]/40 flex-shrink-0 mt-1"></span>
                                        <span x-text="line"></span>
                                    </p>
                                </template>
                            </div>

                            </div>{{-- /briefOpen collapse --}}
                        </div>
                    </div>{{-- /Card 2 — Patient Brief --}}

                    {{-- Card 3 — Medical Alerts (extracted into its own card,
                         08-05 dashboard-row sprint). Hero warning styling when
                         alerts exist; subtle green all-clear when they don't —
                         both branches read the same existing $_clinicalAlerts
                         collection, no new query. Collapsible (08-05 follow-up):
                         alertsOpen is a LOCAL nested x-data scope, same pattern
                         as briefOpen — the status badge stays visible even when
                         collapsed so safety-relevant info is never hidden. --}}
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" x-data="{ alertsOpen: true }">
                        @if(count($_clinicalAlerts))
                        <div class="px-5 py-4 h-full" style="background:#fff5f5;">
                            <button type="button" @click="alertsOpen = !alertsOpen" class="w-full flex items-center justify-between gap-2 text-left">
                                <span class="flex items-center gap-1.5 text-red-700 font-bold text-[10px] tracking-widest uppercase">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                    Medical Alerts
                                    <span class="normal-case font-semibold text-red-600">({{ count($_clinicalAlerts) }})</span>
                                </span>
                                <svg :class="alertsOpen ? 'rotate-180' : ''" class="transition-transform text-red-400 flex-shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="alertsOpen" x-collapse class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($_clinicalAlerts as $alert)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-full
                                        {{ $alert['type'] === 'allergy' ? 'bg-amber-50 text-amber-700 border border-amber-300' : 'bg-red-50 text-red-700 border border-red-300' }}">
                                        {{ $alert['text'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @else
                        <div class="px-5 py-4 h-full" style="background:#f0fdf4;">
                            <button type="button" @click="alertsOpen = !alertsOpen" class="w-full flex items-center justify-between gap-2 text-left">
                                <span class="flex items-center gap-1.5 text-green-700 font-bold text-[10px] tracking-widest uppercase">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    Medical Alerts
                                </span>
                                <svg :class="alertsOpen ? 'rotate-180' : ''" class="transition-transform text-green-400 flex-shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <p x-show="alertsOpen" x-collapse class="text-xs text-green-700 mt-3">No medical alerts</p>
                        </div>
                        @endif
                    </div>{{-- /Card 3 — Medical Alerts --}}

                    {{-- Card 4 — Vitals (moved into the dashboard row, 08-05).
                         Same vitalsOpen collapse/expand behaviour, same
                         x-models — only the collapsed-state summary is new
                         presentation (reads today's already-bound vitals
                         values instead of a generic "recorded" pill). --}}
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-5 py-4">
                        <button type="button" @click="vitalsOpen = !vitalsOpen"
                            class="w-full flex items-center gap-2 text-left group">
                        <span class="text-[10px] font-bold uppercase tracking-[.07em] text-[#6a0f70] flex items-center gap-2">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            Vitals
                        </span>
                        <span class="flex-1 h-px bg-gray-100"></span>
                        <svg :class="vitalsOpen ? 'rotate-180' : ''" class="transition-transform text-gray-400 flex-shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    {{-- Prominent today's-values summary (collapsed state) —
                         same form.* bindings the expanded inputs below use. --}}
                    <div x-show="!vitalsOpen" class="mt-3 flex flex-wrap gap-1.5">
                        <template x-if="!(form.bp_systolic||form.bp_diastolic||form.pulse_rate||form.spo2||form.temperature||form.blood_sugar||form.weight||form.vitals_notes)">
                            <p class="text-xs text-gray-500">Vitals not recorded</p>
                        </template>
                        <span x-show="form.bp_systolic||form.bp_diastolic" class="text-[11px] font-medium text-gray-700 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                            BP <span class="font-bold" x-text="(form.bp_systolic||'–')+'/'+(form.bp_diastolic||'–')"></span>
                        </span>
                        <span x-show="form.pulse_rate" class="text-[11px] font-medium text-gray-700 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                            Pulse <span class="font-bold" x-text="form.pulse_rate"></span>
                        </span>
                        <span x-show="form.spo2" class="text-[11px] font-medium text-gray-700 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                            SpO₂ <span class="font-bold" x-text="form.spo2 + '%'"></span>
                        </span>
                        <span x-show="form.temperature" class="text-[11px] font-medium text-gray-700 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                            Temp <span class="font-bold" x-text="form.temperature + '°C'"></span>
                        </span>
                        <span x-show="form.weight" class="text-[11px] font-medium text-gray-700 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                            Wt <span class="font-bold" x-text="form.weight + ' kg'"></span>
                        </span>
                    </div>

                    <div x-show="vitalsOpen" x-collapse class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Blood Pressure <span class="text-gray-500 font-normal">(mmHg)</span></label>
                            <div class="flex items-center gap-1">
                                <input type="number" x-model="form.bp_systolic" min="40" max="300" placeholder="Sys"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <span class="text-gray-400 font-semibold">/</span>
                                <input type="number" x-model="form.bp_diastolic" min="20" max="200" placeholder="Dia"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Pulse <span class="text-gray-500 font-normal">(bpm)</span></label>
                            <input type="number" x-model="form.pulse_rate" min="20" max="250" placeholder="e.g. 72"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Oxygen — SpO₂ <span class="text-gray-500 font-normal">(%)</span></label>
                            <input type="number" x-model="form.spo2" min="50" max="100" placeholder="e.g. 98"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Temperature <span class="text-gray-500 font-normal">(°C)</span></label>
                            <input type="number" step="0.1" x-model="form.temperature" min="30" max="45" placeholder="e.g. 36.8"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Blood Sugar <span class="text-gray-500 font-normal">(mg/dL)</span></label>
                            <input type="number" x-model="form.blood_sugar" min="20" max="800" placeholder="e.g. 110"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Sugar Reading Type</label>
                            <select x-model="form.blood_sugar_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select —</option>
                                <option value="random">Random (RBS)</option>
                                <option value="fasting">Fasting (FBS)</option>
                                <option value="pp">Post-Prandial (PP)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Weight <span class="text-gray-500 font-normal">(kg)</span></label>
                            <input type="number" step="0.1" x-model="form.weight" min="1" max="400" placeholder="e.g. 65"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Vitals Note <span class="text-gray-500 font-normal">(optional)</span></label>
                            <input type="text" x-model="form.vitals_notes" maxlength="255" placeholder="e.g. BP high — advised to consult physician before extraction"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>

                    </div>{{-- /dashboard row grid (Visit Information / Patient Brief / Medical Alerts / Vitals) --}}
                </div>{{-- /dashboard wrapper --}}

                {{-- IA polish (visual zoning only) — Chapter 2 of 4, the hero
                     section. Extra top margin (beyond the standard space-y-6
                     rhythm) plus the divider mark this as a new chapter, not
                     a continuation of Patient Context above. The 30/40/30
                     layout, and every card inside it, is untouched. --}}
                <div class="mt-4">
                    <div class="tv-zone-heading">Today's Encounter</div>
                    <div class="tv-zone-divider"></div>
                </div>

                {{-- ══════════════════ CLINICAL WORKSPACE -- 40/30/30 ══════════════════
                     Immediately below the (unchanged) dashboard row: Today's
                     Procedures (30%) | Clinical Notes hero (40%) | Intelligence
                     Panel (30%). Every field/card inside each column is the
                     exact existing markup, only relocated -- same x-model/
                     x-show/@click bindings, same getters, same payload.
                     Clinical Notes is the visual anchor (largest, sticky on
                     desktop); Today's Procedures is active clinical workflow
                     (renamed from "Today's Work" -- label only, zero logic
                     change); Intelligence Panel is passive system guidance,
                     visually secondary, also sticky so it stays in view. --}}
                {{-- Shared FDI tooth-chart CSS + toothChartMixin(). @once-guarded
                     and deliberately placed OUTSIDE every x-for, so a custom
                     procedure's optional tooth picker reuses the SAME odontogram
                     the Lab module already uses instead of a second one. --}}
                @include('partials.tooth-chart-assets')

                <div class="lg:grid lg:grid-cols-[3fr_4fr_3fr] lg:gap-6 lg:items-start">

                    {{-- LEFT 30% -- Today's Procedures. Sprint 2/Phase 2:
                         card padding + grid gap tightened (px-5 py-5 → px-4
                         py-4, gap-3 → gap-2.5) to cut unnecessary internal
                         whitespace per spec — Tailwind classes only, no
                         field/logic removed or moved. --}}
                    <div class="space-y-3 min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[.08em] text-gray-500 px-1">Today's Procedures</div>
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-4 py-4">
                    <div class="grid grid-cols-2 gap-2.5">

                        {{-- Phase 3A (Today's Procedures clinical-workflow
                             sprint): reordered into Section 1 Treatment Plan →
                             2 Search Procedure → (empty state) → 3 Today's
                             Procedures hero cards → 4 Add Custom Treatment →
                             5 Recorded Items → 6 Tooth Selection (moved to the
                             bottom). Every x-model/x-show/@click/:class below
                             is the exact same binding as before this sprint —
                             only wrapping markup, ordering and static labels
                             changed. Nothing outside this card (Clinical
                             Notes / Intelligence Panel / Header / Dashboard)
                             was touched. --}}

                        {{-- SECTION 1 — Treatment Plan. Same x-model/@change
                             binding, de-emphasized (smaller label + control)
                             so it reads as a helper, not competing with the
                             procedure cards below. --}}
                        <div class="col-span-2">
                            <label class="text-[11px] font-medium text-gray-500 block mb-1">Treatment Plan</label>
                            <select x-model="form.treatment_plan_id" @change="onPlanChange()"
                                    class="w-full text-xs text-gray-600 border border-gray-100 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#6a0f70] hover:border-gray-200 transition-colors">
                                <option value="">— Select Treatment Plan —</option>
                                <template x-for="plan in treatmentPlans" :key="plan.id">
                                    <option :value="plan.id"
                                            x-text="plan.plan_name + (plan.progress ? ' (' + plan.progress + ')' : '')"></option>
                                </template>
                            </select>
                        </div>

                        {{-- (Removed) SECTION 2 — "Search a procedure". A visit
                             now has exactly TWO doors into Today's Procedures:
                             the selected Treatment Plan, or + Add Custom
                             Treatment. A third, page-level procedure search that
                             set form.treatment_name without recording a
                             procedure was the source of the inconsistency this
                             sprint removes. The catalogue is still reachable —
                             it is the picker inside a custom procedure row. --}}

                        {{-- EMPTY STATE — shown only when nothing has been
                             recorded yet AND no plan is active (so the plan
                             picker below isn't already offering the same
                             action). Reuses the existing addBillingItem()
                             method as its CTA — the exact same function the
                             "Add Custom Treatment" button below calls — no new
                             Alpine state or method. Label standardized
                             (Phase 6 terminology pass) to match every other
                             trigger of this same function. --}}
                        <div class="col-span-2" x-show="!form.treatment_plan_id && visitItems.length === 0">
                            <div class="text-center py-6 px-4 border border-dashed border-gray-200 rounded-lg">
                                <p class="text-xs text-gray-500 mb-3">No procedures selected for today's visit.</p>
                                <button type="button" @click="addBillingItem()"
                                        class="inline-flex items-center gap-1.5 bg-[#6a0f70] hover:bg-[#570c5d] text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Custom Treatment
                                </button>
                            </div>
                        </div>

                        {{-- SECTION 3 — Today's Procedures (hero). Formerly
                             "LAYER 2: Treatment from Plan". Same outer
                             plan-selected visibility rule, same
                             planItemsLoading / planItems.length x-if guards.
                             Only the x-for pi in planItems row markup changed
                             — from a wrapping row of pills into a vertical
                             stack of full-width cards (star / name / tooth /
                             status), per spec. Every binding on every
                             element (togglePlanItem, setPrimaryPlanItem,
                             isPlanItemSelected, isPlanItemPrimary,
                             setWorkOutcome, workOutcomeFor, workOutcomes,
                             toggleItemTooth, itemToothSelected,
                             planItemTeeth, densePicker, bulkOutcome) is
                             unchanged. --}}
                        <div class="col-span-2 bg-purple-50/40 border border-purple-200 rounded-xl p-3.5 shadow-sm" x-show="form.treatment_plan_id">
                            <label class="text-base font-bold text-gray-900 block mb-2">
                                Today's Procedures
                                <span class="font-normal text-gray-500 text-xs ml-1">— from plan</span>
                            </label>
                            <template x-if="planItemsLoading">
                                <p class="text-xs text-gray-500 py-2 italic">Loading plan items…</p>
                            </template>
                            <template x-if="!planItemsLoading && planItems.length === 0">
                                <p class="text-xs text-gray-500 py-2 italic">No items found in this plan.</p>
                            </template>
                            <template x-if="!planItemsLoading && planItems.length > 0">
                                <div>
                                    <p class="text-[10px] text-gray-500 mb-1.5">Tick every treatment done this visit. Star marks the primary one (drives stage-tracker &amp; clinical fields below).</p>
                                    {{-- Delta 4 (R-2): bulk outcome bar — dense mode only. Loops the
                                         existing per-item setter; payload stays per-item. --}}
                                    <div x-show="densePicker" x-cloak class="flex flex-wrap items-center gap-1.5 mb-2">
                                        <span class="text-[10px] text-gray-500">Apply to all selected:</span>
                                        <template x-for="(label, key) in workOutcomes" :key="'bulk-' + key">
                                            <button type="button" @click="bulkOutcome(key)"
                                                    class="px-2 py-0.5 text-[10px] font-semibold border border-gray-200 rounded text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                                                <span x-text="label"></span>
                                            </button>
                                        </template>
                                    </div>
                                    {{-- Delta 3 (R-1) + Phase 3A card layout: dense mode keeps the
                                         scrollable max-height for >6 plan items (FMR / implant
                                         rehab); each item is now a full-width card, not a pill. --}}
                                    {{-- Cards enlarged (visual-dominance pass) — px-3 py-2 → px-4
                                         py-3, rounded-lg → rounded-xl, name text-xs → text-sm,
                                         star 14px → 16px, status-chip row spaced further out.
                                         densePicker's scroll max-height raised 64→80 to fit the
                                         taller cards; same densePicker binding, same scroll
                                         behaviour for >6 items. --}}
                                    <div class="space-y-2 mb-2" :class="densePicker ? 'max-h-80 overflow-y-auto pr-1' : ''">
                                        <template x-for="pi in planItems" :key="pi.id">
                                            <div class="border rounded-xl px-4 py-3 transition-all"
                                                 :class="isPlanItemSelected(pi.id) ? 'bg-white border-[#6a0f70]/50 shadow-sm' : 'bg-white/70 border-gray-200 hover:border-gray-300'">
                                                <div class="flex items-start gap-2">
                                                    <button type="button" x-show="isPlanItemSelected(pi.id)"
                                                            @click="setPrimaryPlanItem(pi)"
                                                            :title="isPlanItemPrimary(pi.id) ? 'Primary treatment for this visit' : 'Set as primary — drives stage-tracker & clinical fields'"
                                                            :aria-label="isPlanItemPrimary(pi.id) ? 'Primary treatment for this visit' : 'Set as primary — drives stage-tracker & clinical fields'"
                                                            :class="isPlanItemPrimary(pi.id) ? 'text-amber-500' : 'text-gray-300 hover:text-amber-400'"
                                                            class="flex-shrink-0 p-2 -m-1.5 transition-colors">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" :fill="isPlanItemPrimary(pi.id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                                    </button>
                                                    <button type="button" @click="togglePlanItem(pi)" class="flex-1 text-left">
                                                        <span class="text-sm font-semibold" :class="isPlanItemSelected(pi.id) ? 'text-gray-900' : 'text-gray-500'" x-text="pi.treatment_name"></span>
                                                        <svg x-show="isPlanItemSelected(pi.id)" class="inline-block ml-1 text-[#6a0f70]" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                        <div x-show="pi.tooth_number" class="text-xs text-gray-500 mt-1" x-text="'Tooth ' + pi.tooth_number"></div>
                                                    </button>
                                                </div>
                                                {{-- Slice 2.4b — what happened to THIS treatment today.
                                                     A fact about today only. It never says the
                                                     treatment is finished overall, and nothing is
                                                     derived from it in this slice. --}}
                                                <div x-show="isPlanItemSelected(pi.id)" class="flex flex-wrap items-center gap-1.5 mt-3 ml-7">
                                                    <template x-for="(label, key) in workOutcomes" :key="key">
                                                        <button type="button" @click="setWorkOutcome(pi, key)"
                                                                :class="workOutcomeFor(pi) === key
                                                                    ? 'bg-[#6a0f70] border-[#380740] text-white'
                                                                    : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70]'"
                                                                class="px-2.5 py-1.5 text-[11px] font-semibold border rounded-md transition-colors">
                                                            <span x-text="label"></span>
                                                        </button>
                                                    </template>
                                                </div>

                                                {{-- Narrow to specific teeth when this plan item spans more than one --}}
                                                <div x-show="isPlanItemSelected(pi.id) && planItemTeeth(pi).length > 1" class="flex flex-wrap items-center gap-1 mt-1 ml-7">
                                                    <span class="text-[10px] text-gray-500 mr-1">Done today on:</span>
                                                    <template x-for="t in planItemTeeth(pi)" :key="t">
                                                        <button type="button" @click="toggleItemTooth(pi, t)"
                                                                :class="itemToothSelected(pi, t) ? 'bg-purple-100 border-purple-300 text-[#6a0f70]' : 'bg-white border-gray-200 text-gray-500'"
                                                                class="px-1.5 py-0.5 text-[10px] font-semibold border rounded">
                                                            T<span x-text="t"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    {{-- (Removed) the in-panel "Add Custom Treatment"
                                         button + its own typeahead. It duplicated the
                                         Section 4 button below with different behaviour
                                         (it wrote a single _isOther line). One button,
                                         one behaviour, always visible. --}}
                                </div>
                            </template>
                        </div>

                        {{-- SECTION 5 — Recorded Items (UNIFIED WORK LIST).
                             Moved ABOVE "Add Custom Treatment" per the
                             visual-dominance pass (resolves the earlier
                             Section-4-vs-diagram ordering discrepancy in
                             favour of the diagram). Same visitItems array,
                             same save payload, same collapsed/expanded row
                             toggle (item._open), same splice() remove —
                             only density tightened (p-2.5→p-2,
                             space-y-2→space-y-1.5, gap-2→gap-1.5) for the
                             "increase information density" requirement. --}}
                        <div class="col-span-2" x-show="visitItems.length > 0">
                            <label class="text-xs font-semibold text-gray-600 flex items-center gap-1.5 mb-1.5">
                                Recorded items
                                <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-[#6a0f70] text-white text-[10px] font-bold" x-text="visitItems.length"></span>
                            </label>
                            <div class="space-y-1.5">
                                {{-- Stable key: an index key makes Alpine reuse DOM nodes
                                     across removals, which would strand a row's tooth
                                     picker / catalogue box on the wrong item. --}}
                                <template x-for="(item, idx) in visitItems"
                                          :key="item.treatment_plan_item_id ? 'plan-' + item.treatment_plan_item_id : 'custom-' + item._uid">
                                    <div class="bg-purple-50 border border-purple-100 rounded-lg p-2 hover:border-purple-200 transition-colors">

                                        {{-- COLLAPSED one-line row. Identical for a planned and a
                                             custom procedure: past the point of entry the two are
                                             the same kind of thing, only their origin differs. --}}
                                        <div x-show="!item._open" class="flex items-center gap-1.5">
                                            {{-- Star: a custom procedure can be the visit's primary
                                                 treatment too (plan items star from their own card
                                                 above), so the stage tracker and procedure worksheet
                                                 work on an unplanned visit exactly as on a planned one. --}}
                                            <button type="button" x-show="!item.treatment_plan_item_id && item.treatment_name"
                                                    @click="setPrimaryCustomItem(item)"
                                                    :title="isCustomItemPrimary(item) ? 'Primary treatment for this visit' : 'Set as primary — drives stage-tracker & clinical fields'"
                                                    :aria-label="isCustomItemPrimary(item) ? 'Primary treatment for this visit' : 'Set as primary treatment'"
                                                    :class="isCustomItemPrimary(item) ? 'text-amber-500' : 'text-gray-300 hover:text-amber-400'"
                                                    class="flex-shrink-0 transition-colors">
                                                <svg width="13" height="13" viewBox="0 0 24 24" :fill="isCustomItemPrimary(item) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            </button>
                                            <span class="text-xs font-semibold text-gray-800 truncate" x-text="item.treatment_name || '— unnamed —'"></span>
                                            <span x-show="item.tooth_number" class="text-xs text-gray-500 flex-shrink-0" x-text="'T' + item.tooth_number"></span>
                                            {{-- Legacy display only: Material / Option is no longer
                                                 enterable anywhere — material and subtype belong to
                                                 the Lab Case. Visits saved before this change still
                                                 show what they already carry. --}}
                                            <span x-show="item.material_option" class="text-xs text-gray-500 flex-shrink-0" x-text="item.material_option"></span>
                                            <span x-show="item.work_outcome" x-cloak class="text-[9px] font-bold text-[#6a0f70] bg-purple-100 border border-purple-200 rounded px-1 flex-shrink-0" x-text="workOutcomes[item.work_outcome]"></span>
                                            <span x-show="item.lab_required" x-cloak class="text-[9px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 flex-shrink-0">LAB</span>
                                            <span class="text-xs text-gray-600 ml-auto flex-shrink-0" x-text="'Rs. ' + fmt(item.suggested_price || 0)"></span>
                                            <button type="button" @click="item._open = true"
                                                    class="p-2 -m-1 text-gray-400 hover:text-[#6a0f70] rounded transition-colors flex-shrink-0" title="Edit item" aria-label="Edit item">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                            </button>
                                            <button type="button" @click="removeVisitItem(idx)"
                                                    class="p-2 -m-1 text-red-300 hover:text-red-500 rounded transition-colors flex-shrink-0" title="Remove item" aria-label="Remove item">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </div>

                                        {{-- EXPANDED editor. A custom procedure gets the catalogue
                                             picker, the shared odontogram and the Lab Required
                                             toggle; a plan-sourced procedure keeps the exact editor
                                             it has always had (free-text name + free-text tooth), so
                                             Treatment Plan behaviour is untouched. Material / Option
                                             is gone from BOTH — it duplicated the Lab Case. --}}
                                        <div x-show="item._open" class="flex items-start gap-2">
                                            <div class="flex-1 min-w-0 space-y-2">

                                                {{-- Procedure --}}
                                                <div>
                                                    <label class="text-[10px] font-semibold text-gray-500 block mb-1">Procedure <span class="text-red-400">*</span></label>

                                                    {{-- Plan-sourced: unchanged control. --}}
                                                    <template x-if="item.treatment_plan_item_id">
                                                        <input type="text" x-model="item.treatment_name" @input.debounce.400ms="_checkRepeatWork()"
                                                               class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                               placeholder="Treatment name">
                                                    </template>

                                                    {{-- Custom: pick from the clinic catalogue, or type
                                                         a procedure the catalogue does not have yet —
                                                         recording today's work must never be blocked on
                                                         maintaining the master list. --}}
                                                    <template x-if="!item.treatment_plan_item_id">
                                                        <div class="relative" @click.outside="item._pickerOpen = false">
                                                            <div class="flex items-center gap-1.5 border border-gray-200 rounded px-2 py-1.5 bg-white focus-within:border-[#6a0f70] transition-colors">
                                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                                                <input type="text"
                                                                       x-model="item._search"
                                                                       @focus="item._pickerOpen = true"
                                                                       @input="item._pickerOpen = true"
                                                                       @keydown.escape="item._pickerOpen = false"
                                                                       @keydown.enter.prevent="const s = procedureSuggestions(item); s.length ? selectProcedure(item, s[0]) : useTypedProcedure(item)"
                                                                       @blur="useTypedProcedure(item)"
                                                                       placeholder="Select or type a procedure…"
                                                                       class="flex-1 min-w-0 text-xs outline-none bg-transparent"
                                                                       autocomplete="off">
                                                                <button x-show="item._search" type="button" @click="clearProcedure(item)" aria-label="Clear procedure"
                                                                        class="text-gray-400 hover:text-gray-600 text-base leading-none flex-shrink-0">&times;</button>
                                                            </div>
                                                            <div x-show="item._pickerOpen" x-cloak
                                                                 class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-48 overflow-y-auto">
                                                                <template x-for="row in procedureSuggestions(item)" :key="row.name">
                                                                    <button type="button" @mousedown.prevent="selectProcedure(item, row)"
                                                                            class="w-full flex items-center justify-between gap-2 text-left px-3 py-2 text-xs hover:bg-purple-50 hover:text-[#6a0f70] transition-colors">
                                                                        <span class="truncate" x-text="row.name"></span>
                                                                        <span class="text-[10px] text-gray-400 flex-shrink-0" x-show="row.price" x-text="'Rs. ' + fmt(row.price)"></span>
                                                                    </button>
                                                                </template>
                                                                <div x-show="procedureSuggestions(item).length === 0" class="px-3 py-2 text-[11px] text-gray-500 leading-snug">
                                                                    Not in the clinic catalogue. Press <strong>Enter</strong> to record it for this visit anyway.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-2">
                                                    {{-- Tooth — OPTIONAL. Scaling, a consultation and a
                                                         full-mouth procedure have no single tooth, and
                                                         a blank tooth never blocks saving. --}}
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-gray-500 block mb-1">Tooth <span class="font-normal text-gray-400">(optional)</span></label>

                                                        {{-- Plan-sourced: unchanged control. --}}
                                                        <template x-if="item.treatment_plan_item_id">
                                                            <input type="text" x-model="item.tooth_number" @input.debounce.400ms="_checkRepeatWork()"
                                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                                   placeholder="e.g. 26">
                                                        </template>

                                                        {{-- Custom: the SAME FDI odontogram the Lab module
                                                             uses (partials.tooth-chart). Nested x-data so
                                                             toothChartMixin()'s toggleTooth(item, t) does
                                                             not collide with this form's own visit-level
                                                             toggleTooth(code) below. --}}
                                                        <template x-if="!item.treatment_plan_item_id">
                                                            <div x-data="toothChartMixin()" @click="$nextTick(() => onItemToothChange(item))">
                                                                @include('partials.tooth-chart', ['target' => 'item', 'pickerId' => "'tvCustomItem'", 'buttonLabel' => 'Select tooth (optional)'])
                                                            </div>
                                                        </template>
                                                    </div>

                                                    {{-- Suggested Price — pre-filled from the catalogue
                                                         when the procedure is known, always editable,
                                                         and feeds Billing Preview + the front desk's
                                                         billing prompt exactly like a plan procedure. --}}
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-gray-500 block mb-1">Suggested Price (Rs. )</label>
                                                        <input type="number" x-model="item.suggested_price" min="0"
                                                               class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                               placeholder="0">
                                                    </div>
                                                </div>

                                                {{-- Treatment status — the SAME three clinical facts a
                                                     plan procedure records, now available on ad-hoc work
                                                     too. THIS is how a doctor tells Dentfluence a
                                                     treatment is finished: the latest outcome recorded
                                                     for a procedure on a tooth IS its current state, and
                                                     repeat-work detection reads exactly that. Leaving it
                                                     blank records no claim, and the treatment stays open
                                                     — which is why a mid-course RCT is never flagged. --}}
                                                <template x-if="!item.treatment_plan_item_id">
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-gray-500 block mb-1">Treatment status <span class="font-normal text-gray-400">(optional)</span></label>
                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                            <template x-for="(label, key) in workOutcomes" :key="'custom-outcome-' + key">
                                                                <button type="button" @click="setItemOutcome(item, key)"
                                                                        :class="itemOutcomeFor(item) === key
                                                                            ? 'bg-[#6a0f70] border-[#380740] text-white'
                                                                            : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70]'"
                                                                        class="px-2.5 py-1 text-[11px] font-semibold border rounded-md transition-colors">
                                                                    <span x-text="label"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                        <p x-show="itemOutcomeFor(item) === 'completed_today'" x-cloak class="text-[10px] text-green-700 mt-1">
                                                            Marks this procedure finished on this tooth. Recording it again later will be flagged as possible repeat work.
                                                        </p>
                                                    </div>
                                                </template>

                                                {{-- Lab Required? — OFF unless the catalogue says this
                                                     procedure needs lab work. ON reveals the existing
                                                     Lab Case card (right column); there is no second
                                                     lab implementation and no per-item lab fields. --}}
                                                <template x-if="!item.treatment_plan_item_id">
                                                    <div>
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-[10px] font-semibold text-gray-500">Lab Required?</span>
                                                            <button type="button" @click="toggleItemLab(item)"
                                                                    :aria-pressed="item.lab_required ? 'true' : 'false'"
                                                                    aria-label="Lab required for this procedure"
                                                                    :class="item.lab_required ? 'bg-amber-600 border-amber-700' : 'bg-white border-gray-300'"
                                                                    class="relative inline-flex h-5 w-9 items-center rounded-full border-2 transition-colors flex-shrink-0">
                                                                <span :class="item.lab_required ? 'translate-x-4 bg-white' : 'translate-x-0.5 bg-gray-300'"
                                                                      class="inline-block h-3.5 w-3.5 transform rounded-full transition-transform"></span>
                                                            </button>
                                                        </div>
                                                        <p x-show="item.lab_required" x-cloak class="text-[10px] text-amber-700 mt-1">
                                                            Fill the lab details in the <strong>Lab Case</strong> card on the right.
                                                        </p>
                                                    </div>
                                                </template>

                                            </div>
                                            <div class="mt-5 flex-shrink-0 flex flex-col gap-1">
                                                <button type="button" @click="item._open = false"
                                                        class="p-2 -m-1 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors" title="Done editing" aria-label="Done editing">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                </button>
                                                <button type="button" @click="removeVisitItem(idx)"
                                                        class="p-2 -m-1 text-red-300 hover:text-red-500 hover:bg-red-50 rounded transition-colors" title="Remove item" aria-label="Remove item">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- SECTION 4 — Add Custom Treatment (renamed from
                             "Add Other Treatment", Phase 6 terminology pass
                             — same three variants "Other Treatment"/"Not in
                             Plan"/"Other / Not in Plan" now read one way
                             everywhere on this page). Moved below Recorded
                             Items (visual-dominance pass). Same
                             addBillingItem() binding as before; de-emphasized
                             (plain text-style button instead of a large
                             dashed box) per spec. --}}
                        <div class="col-span-2">
                            <button type="button" @click="addBillingItem()"
                                    class="w-full flex items-center justify-center gap-1.5 text-xs font-semibold text-[#6a0f70] py-2 rounded-lg hover:bg-purple-50 transition-colors">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Custom Treatment
                            </button>
                        </div>

                        {{-- SECTION 6 — Tooth Selection. Moved to the bottom
                             of the column per spec — supports procedures, so
                             it no longer sits between the picker and the
                             recorded list. Odontogram markup/bindings
                             completely unchanged. --}}
                        <div class="col-span-2 max-w-xs">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Tooth No.</label>
                            <div class="relative">
                                <button type="button" @click="toothChartOpen = !toothChartOpen"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 text-left focus:outline-none focus:border-[#6a0f70] flex items-center justify-between"
                                        :class="form.tooth_number ? 'text-gray-800 font-semibold' : 'text-gray-500'">
                                    <span x-text="form.tooth_number || 'Select tooth(s)…'"></span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>

                                {{-- Tooth chart dropdown --}}
                                <div x-show="toothChartOpen"
                                     @click.outside="toothChartOpen = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="absolute z-50 top-full left-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg p-3"
                                     style="min-width:300px">

                                    <div class="text-[10px] text-gray-500 mb-2 flex items-center justify-between">
                                        <span>Click to select. Click again to deselect. Multiple allowed.</span>
                                        <button type="button" @click="selectedTeeth=[]; form.tooth_number=''" class="text-red-400 hover:text-red-600 font-semibold">Clear</button>
                                    </div>

                                    {{-- Upper jaw --}}
                                    <div class="text-[9px] text-gray-500 text-center mb-1 uppercase tracking-wide">Upper (Maxilla)</div>
                                    <div class="flex justify-center items-end gap-0.5 mb-1">
                                        @foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28] as $t)
                                        @php $childCode = config('dental_notation.permanent_to_primary')[$t] ?? null; @endphp
                                        <div class="flex flex-col items-center gap-0.5" x-data="{ code: {{ $t }} }">
                                            <button type="button"
                                                    @click="toggleTooth(code)"
                                                    :class="selectedTeeth.includes(code) ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                                                    :aria-label="'Tooth ' + code + (selectedTeeth.includes(code) ? ' — selected' : '')"
                                                    class="w-9 h-9 text-[10px] font-bold border rounded flex items-center justify-center transition-all"
                                                    x-text="code"></button>
                                            @if($childCode)
                                            <button type="button"
                                                    @click.stop="const nc = (code === {{ $t }} ? {{ $childCode }} : {{ $t }});
                                                        const idx = selectedTeeth.indexOf(code);
                                                        if (idx >= 0) { selectedTeeth.splice(idx, 1); selectedTeeth.push(nc); form.tooth_number = selectedTeeth.slice().sort((a,b)=>a-b).join(', '); }
                                                        code = nc;"
                                                    :class="code === {{ $childCode }} ? 'bg-pink-100 text-pink-600 border-pink-300' : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60'"
                                                    :title="code === {{ $childCode }} ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                                    :aria-label="code === {{ $childCode }} ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                                    class="w-8 h-3 text-[8px] font-bold border rounded flex items-center justify-center"
                                                    x-text="code === {{ $childCode }} ? 'P' : 'A'"></button>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>

                                    {{-- Divider --}}
                                    <div class="border-t border-dashed border-gray-200 my-1.5"></div>

                                    {{-- Lower jaw --}}
                                    <div class="flex justify-center items-start gap-0.5 mb-1">
                                        @foreach([48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $t)
                                        @php $childCode = config('dental_notation.permanent_to_primary')[$t] ?? null; @endphp
                                        <div class="flex flex-col items-center gap-0.5" x-data="{ code: {{ $t }} }">
                                            <button type="button"
                                                    @click="toggleTooth(code)"
                                                    :class="selectedTeeth.includes(code) ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                                                    :aria-label="'Tooth ' + code + (selectedTeeth.includes(code) ? ' — selected' : '')"
                                                    class="w-9 h-9 text-[10px] font-bold border rounded flex items-center justify-center transition-all"
                                                    x-text="code"></button>
                                            @if($childCode)
                                            <button type="button"
                                                    @click.stop="const nc = (code === {{ $t }} ? {{ $childCode }} : {{ $t }});
                                                        const idx = selectedTeeth.indexOf(code);
                                                        if (idx >= 0) { selectedTeeth.splice(idx, 1); selectedTeeth.push(nc); form.tooth_number = selectedTeeth.slice().sort((a,b)=>a-b).join(', '); }
                                                        code = nc;"
                                                    :class="code === {{ $childCode }} ? 'bg-pink-100 text-pink-600 border-pink-300' : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60'"
                                                    :title="code === {{ $childCode }} ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                                    :aria-label="code === {{ $childCode }} ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)'"
                                                    class="w-8 h-3 text-[8px] font-bold border rounded flex items-center justify-center"
                                                    x-text="code === {{ $childCode }} ? 'P' : 'A'"></button>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="text-[9px] text-gray-500 text-center mt-1 uppercase tracking-wide">Lower (Mandible)</div>

                                    <button type="button" @click="toothChartOpen = false"
                                            class="mt-2 w-full text-xs font-semibold text-white bg-[#6a0f70] hover:bg-[#570c5d] rounded-lg py-1.5 transition-colors">
                                        Done
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                    </div>
                    </div>{{-- /Today's Procedures --}}

                    {{-- CENTER 40% -- Clinical Notes (Hero). Phase 2 (08-05
                         Clinical Documentation Hero sprint): visual-only
                         restructure of this ONE card into header / toolbar /
                         writing area / footer. The underlying control is
                         still the exact same plain textarea, same x-model
                         (form.notes) and same dusk selector (visit-notes),
                         unchanged — no rich-text/contenteditable, no new
                         editor library, no new Alpine state. The toolbar
                         icons (Bold/Italic/Underline/Bullet List/Insert
                         Image/Insert Link) and the mic (Dictation) icon are
                         ALL decorative — plain inert buttons with no @click
                         and no x-*, same "reserves visual space without
                         implementing"
                         pattern already established for the mic glyph before
                         this sprint. The footer's character count reads the
                         existing form.notes value inline (x-text derived
                         display, not new state — same pattern as every other
                         x-text in this file) and the AI tip is static text.
                         Eyebrow label above the card unchanged from the prior
                         alignment-fix sprint. --}}
                    <div class="space-y-3 min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[.08em] text-gray-500 px-1">Clinical Documentation</div>
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden lg:sticky lg:top-4">

                        {{-- Header --}}
                        <div class="px-6 pt-5 pb-3">
                            <div class="tv-section-legend !mb-0">Today's Clinical Notes</div>
                        </div>

                        {{-- Toolbar — decorative only, see comment above. --}}
                        <div class="flex items-center gap-0.5 px-5 py-2 border-y border-gray-100 bg-gray-50/50 flex-wrap">
                            <button type="button" title="Bold — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 0 8H6z"/><path d="M6 12h9a4 4 0 0 1 0 8H6z"/></svg>
                            </button>
                            <button type="button" title="Italic — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
                            </button>
                            <button type="button" title="Underline — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" y1="20" x2="20" y2="20"/></svg>
                            </button>
                            <span class="w-px h-4 bg-gray-200 mx-1.5"></span>
                            <button type="button" title="Bullet list — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.2" fill="currentColor" stroke="none"/></svg>
                            </button>
                            <span class="w-px h-4 bg-gray-200 mx-1.5"></span>
                            <button type="button" title="Insert image — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            </button>
                            <button type="button" title="Insert link — coming soon" class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </button>
                            <span class="flex-1"></span>
                            <button type="button" title="Voice dictation — coming soon" class="flex items-center gap-1.5 px-2 py-1 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                                <span class="text-[10px] font-medium">Dictate</span>
                            </button>
                        </div>

                        {{-- Writing area — the hero. Same x-model/dusk as
                             before; only rows/padding/typography changed to
                             read as a clinical note surface rather than a
                             cramped form field (~10–15 visible lines). --}}
                        <div class="px-6 py-5">
                            <textarea x-model="form.notes" rows="14"
                                      dusk="visit-notes"
                                      placeholder="Document what was done today, observations, patient response…"
                                      class="w-full min-h-[360px] text-[16px] leading-loose text-gray-800 border-0 focus:outline-none focus:ring-0 resize-none bg-transparent placeholder:text-gray-500"></textarea>
                        </div>

                        {{-- Footer — character count reads the existing
                             form.notes binding inline (no new state); AI tip
                             is static, decorative text. --}}
                        <div class="flex items-center justify-between gap-3 px-6 py-3 border-t border-gray-100 bg-gray-50/40">
                            <span class="text-[11px] text-gray-500" x-text="(form.notes ? form.notes.length : 0) + ' characters'"></span>
                            <span class="flex items-center gap-1.5 text-[11px] text-gray-500">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-purple-300"><path d="M12 3v4M3 12h4M12 21v-4M21 12h-4M6 6l2.5 2.5M18 6l-2.5 2.5M6 18l2.5-2.5M18 18l-2.5-2.5"/></svg>
                                Tip: note findings, procedures done, and patient response for a complete record.
                            </span>
                        </div>

                    </div>
                    </div>{{-- /Clinical Notes hero --}}

                    {{-- RIGHT 30% -- Intelligence Panel. Sprint 2/Phase 2:
                         added min-w-0 to match the other two columns (Today's
                         Procedures / Clinical Notes already had it) so all
                         three grid tracks behave identically at narrow
                         widths — pure layout parity, no content change. --}}
                    <div class="space-y-3 min-w-0 lg:sticky lg:top-4">
                    <div class="text-[10px] font-bold uppercase tracking-[.08em] text-gray-500 px-1">Intelligence Panel</div>

                {{-- Phase 3B (Intelligence Panel decision-support sprint):
                     reorganized into six independent white cards — Treatment
                     Progress / Billing Preview / Lab Case / Completion /
                     Next Visit / Today's Summary — each with the same
                     bg-white border rounded-lg shadow-sm px-4 py-3.5
                     treatment and the same .tv-section-legend title style.
                     The old "When you save, Dentfluence will…" glue-text
                     legend was removed as superseded (each card now titles
                     itself). Every x-show/x-model/@click/getter below is
                     the exact same binding used before this sprint — only
                     wrapping markup, card boundaries and static labels
                     changed. Two spec sub-items had no existing getter to
                     reuse and were intentionally left out rather than
                     invented (see sprint report): a distinct "Planned
                     Stage" value (only currentStages + current_stage exist)
                     and an "Existing Lab Case" lookup (no such binding is
                     wired into this partial). --}}

                {{-- CARD 1 — Treatment Progress. Same treatment-selected
                     visibility gate, same currentStages/toggleStage/form.completed_stages/
                     form.current_stage bindings. Added a plain-language
                     "Current stage" line and a "n / m done" count, both
                     derived inline from the SAME existing reactive state
                     (no new getters or methods). --}}
                <div x-show="form.treatment_name" class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Treatment Progress</div>

                    <div x-show="currentStages && Object.keys(currentStages).length > 0">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="text-xs text-gray-600">
                                Current:
                                <span class="font-semibold text-gray-800" x-text="currentStages[form.current_stage] || '— not started —'"></span>
                            </p>
                            <span class="text-[10px] font-semibold text-gray-500 flex-shrink-0" x-text="form.completed_stages.length + ' / ' + Object.keys(currentStages).length + ' done'"></span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(label, key) in currentStages" :key="key">
                                <button type="button"
                                        @click="toggleStage(key)"
                                        :class="form.completed_stages.includes(key) ? 'tv-stage-btn done' : (form.current_stage === key ? 'tv-stage-btn current' : 'tv-stage-btn')"
                                        x-text="label"></button>
                            </template>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Done &nbsp;
                            <span class="inline-block w-2 h-2 rounded-full bg-[#6a0f70] mr-1"></span>Current
                        </p>
                    </div>
                </div>

                {{-- CARD 2 — Billing Preview. Same visitItems-not-empty
                     visibility gate, same visitItems array, same fmt()/visitItemsTotal
                     getter as before — now listed per item (name + price)
                     instead of one joined sentence, so it reads like a
                     receipt. No new state. (Phase 6 cleanup: the old
                     single-string billingPreview getter this card used to
                     read from was confirmed to have zero remaining
                     references anywhere in the codebase and was removed
                     from the script file.) --}}
                <div x-show="visitItems.length > 0" x-cloak class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Billing Preview</div>
                    <div class="space-y-1.5">
                        <template x-for="(item, idx) in visitItems" :key="idx">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="flex items-center gap-1.5 text-gray-700 min-w-0">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span class="truncate" x-text="item.treatment_name || '— unnamed —'"></span>
                                </span>
                                <span class="font-semibold text-gray-800 flex-shrink-0" x-text="'Rs. ' + fmt(item.suggested_price || 0)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-gray-100">
                        <span class="text-xs font-bold text-gray-800">Total</span>
                        <span class="text-sm font-bold text-[#6a0f70]" x-text="'Rs. ' + fmt(visitItemsTotal)"></span>
                    </div>
                    <p class="text-[10px] text-gray-500 mt-2">Front desk will be prompted to bill this when you save.</p>
                </div>

                {{-- CARD 3 — Lab Case. Same labNeeded / labCase.* / labVendorsList /
                     labSubtypes bindings as before. Now always visible with a
                     "Not required" state instead of disappearing entirely when
                     labNeeded is false, and restyled from its own amber-bordered
                     box into the same white-card treatment as the other five
                     cards (accent color kept only for the inner badge/switch/
                     text). Same labCase.enabled collapse condition, same
                     lab_vendor_id / work_category / work_subtype / priority /
                     expected_return_date / instructions fields, unchanged. --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Lab Case</div>

                    <div x-show="!labNeeded" class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        Not required for this procedure.
                    </div>

                    <div x-show="labNeeded" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 min-w-0">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
                                Required — <span class="truncate" x-text="labReasonLabel"></span>
                            </span>
                            <button type="button" @click="labCase.enabled = !labCase.enabled"
                                    :class="labCase.enabled ? 'bg-amber-600 border-amber-700' : 'bg-white border-amber-300'"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full border-2 transition-colors flex-shrink-0">
                                <span :class="labCase.enabled ? 'translate-x-4 bg-white' : 'translate-x-0.5 bg-amber-300'"
                                      class="inline-block h-3.5 w-3.5 transform rounded-full transition-transform"></span>
                            </button>
                        </div>

                        <div x-show="labCase.enabled" x-collapse class="space-y-2.5 pt-2.5 border-t border-amber-100">
                            <div class="grid grid-cols-1 gap-2.5">

                                {{-- Lab Vendor --}}
                                <div class="col-span-2">
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Lab / Vendor</label>
                                    <select x-model="labCase.lab_vendor_id"
                                            class="w-full text-xs border border-amber-200 bg-amber-50/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-amber-500">
                                        <option value="">— Select lab —</option>
                                        @foreach($labVendorsList as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Work Category --}}
                                <div>
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Work Category</label>
                                    <select x-model="labCase.work_category" @change="labCase.work_subtype = ''"
                                            class="w-full text-xs border border-amber-200 bg-amber-50/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-amber-500">
                                        <option value="">— Select category —</option>
                                        @foreach(\App\Models\LabCase::WORK_CATEGORIES as $cat => $subtypes)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Work Subtype (dynamic based on category) --}}
                                <div>
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Subtype / Material</label>
                                    <select x-model="labCase.work_subtype"
                                            class="w-full text-xs border border-amber-200 bg-amber-50/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-amber-500">
                                        <option value="">— Select subtype —</option>
                                        <template x-for="st in labSubtypes" :key="st">
                                            <option :value="st" x-text="st"></option>
                                        </template>
                                    </select>
                                </div>

                                {{-- Priority --}}
                                <div>
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Priority</label>
                                    <div class="flex gap-1.5">
                                        @foreach(['routine' => 'Routine', 'urgent' => 'Urgent', 'express' => 'Express'] as $pKey => $pLabel)
                                        <button type="button" @click="labCase.priority = '{{ $pKey }}'"
                                                :class="labCase.priority === '{{ $pKey }}'
                                                    ? '{{ $pKey === 'routine' ? 'bg-gray-200 border-gray-400 text-gray-800' : ($pKey === 'urgent' ? 'bg-amber-400 border-amber-600 text-white' : 'bg-red-500 border-red-700 text-white') }}'
                                                    : 'bg-white border-gray-200 text-gray-500 hover:border-gray-400'"
                                                class="flex-1 text-[11px] font-semibold py-1 rounded-lg border transition-all">
                                            {{ $pLabel }}
                                        </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Expected Return Date --}}
                                <div>
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Expected Return</label>
                                    <input type="date" x-model="labCase.expected_return_date"
                                           class="w-full text-xs border border-amber-200 bg-amber-50/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-amber-500">
                                </div>

                                {{-- Instructions --}}
                                <div class="col-span-2">
                                    <label class="text-[11px] font-semibold text-amber-800 block mb-1">Lab Instructions</label>
                                    <textarea x-model="labCase.instructions" rows="2"
                                              placeholder="Shade, special instructions, try-in date…"
                                              class="w-full text-xs border border-amber-200 bg-amber-50/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-amber-500 resize-none"></textarea>
                                </div>

                            </div>
                        </div>
                        <p x-show="labCase.enabled" class="text-[10px] text-amber-600 mt-2">A <strong>Draft</strong> lab case will be created when you save this visit.</p>
                        <p x-show="!labCase.enabled" class="text-[10px] text-amber-600 mt-2">Create it now, or send it from the Lab module later.</p>
                    </div>
                </div>

                {{-- CARD 4 — Completion. Same form.mark_treatment_complete
                     payload key as before (was a raw checkbox); now two
                     decision buttons that write to the SAME property via
                     direct assignment — the same pattern this file already
                     uses for the Lab Case toggle switch above. No new
                     Alpine state, no new payload key. Same
                     plan-selected visibility gate, same
                     allPlannedCompletedToday getter. --}}
                <div x-show="form.treatment_plan_id" x-cloak class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Treatment Completion</div>
                    <p x-show="allPlannedCompletedToday && !form.mark_treatment_complete" class="text-[11px] font-semibold text-green-700 mb-2">All planned work today is marked “Treatment Complete”.</p>
                    <div class="space-y-1.5">
                        <button type="button" @click="form.mark_treatment_complete = false"
                                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border text-left transition-colors"
                                :class="!form.mark_treatment_complete ? 'border-gray-300 bg-gray-50' : 'border-gray-200 hover:border-gray-300'">
                            <span class="w-3.5 h-3.5 rounded-full border-2 flex-shrink-0" :class="!form.mark_treatment_complete ? 'border-gray-500 bg-gray-500' : 'border-gray-300'"></span>
                            <span class="text-xs font-medium text-gray-700">Continue Treatment</span>
                        </button>
                        <button type="button" @click="form.mark_treatment_complete = true"
                                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border text-left transition-colors"
                                :class="form.mark_treatment_complete ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                            <span class="w-3.5 h-3.5 rounded-full border-2 flex-shrink-0" :class="form.mark_treatment_complete ? 'border-green-600 bg-green-600' : 'border-gray-300'"></span>
                            <span class="text-xs font-medium text-gray-700">Mark Complete</span>
                        </button>
                    </div>
                    <p x-show="form.mark_treatment_complete" class="text-[10px] text-amber-600 mt-2">This will mark the linked treatment plan as completed and schedule a 6-month recall task for the front desk.</p>
                </div>

                {{-- CARD 5 — Next Visit. Same setNextVisitIn()/isNextVisitIn()/
                     form.next_visit_date/form.next_visit_type bindings —
                     spacing tightened only (gap-3→gap-2, mb-2→mb-1.5). --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Next Visit</div>
                    <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                        @foreach([2 => '2 days', 7 => '1 week', 14 => '2 weeks'] as $days => $chip)
                        <button type="button" @click="setNextVisitIn({{ $days }})"
                                class="px-2.5 py-1 text-[11px] font-semibold border rounded-lg transition-colors"
                                :class="isNextVisitIn({{ $days }})
                                    ? 'bg-[#6a0f70] border-[#380740] text-white'
                                    : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70]'">
                            {{ $chip }}
                        </button>
                        @endforeach
                        <span class="text-[10px] text-gray-500 ml-1">or pick a date below</span>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <label class="text-[11px] font-semibold text-gray-600 block mb-1">Next Visit Date</label>
                            <input type="date" x-model="form.next_visit_date"
                                   class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-gray-600 block mb-1">Next Visit For</label>
                            <select x-model="form.next_visit_type"
                                    class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select —</option>
                                <option>Continue Treatment</option><option>Review</option>
                                <option>RCT Next Step</option><option>Crown Try-in</option>
                                <option>Crown Cementation</option><option>Implant Review</option>
                                <option>Suture Removal</option><option>Recall</option><option>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- CARD 5b — Reception Next Action (Visit → Next Action, 08-14).

                     The doctor answers "I finished this patient — what does
                     reception need to do next?" ONCE, here, and is done. On
                     save this becomes a canonical follow_ups row that surfaces
                     to staff on its DUE date. No re-entry in tomorrow's Daily
                     Huddle.

                     Deliberately collapsed to a single button at rest: a visit
                     needing no reception action costs the doctor zero extra
                     fields. Three inputs per action — what / when / say what.

                     Distinct from CARD 5 above: "Next Visit" is clinical intent
                     for the next appointment (printed on the case sheet); this
                     is a task for the front desk. Different owners, different
                     records. --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Reception Next Action</div>

                    <template x-if="!nextActions.length">
                        <p class="text-[10px] text-gray-500 mb-2">Nothing needed from reception after this visit.</p>
                    </template>

                    <div class="space-y-2">
                        <template x-for="(na, idx) in nextActions" :key="na._uid">
                            <div class="border border-gray-200 rounded-lg p-2.5 bg-gray-50/60">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    {{-- Options rendered by Blade, not x-for. ACTION_TYPES is a
                                         compile-time PHP constant, and an x-model select whose
                                         options are built by a nested x-for can initialise before
                                         those options exist — which would show "Wellness Call"
                                         while state actually held a different key. --}}
                                    <select x-model="na.action_type"
                                            class="flex-1 text-xs font-semibold border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-[#6a0f70]">
                                        @foreach(\App\Services\Clinical\VisitNextActionService::ACTION_TYPES as $naKey => $naCfg)
                                        <option value="{{ $naKey }}">{{ $naCfg['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" @click="removeNextAction(idx)"
                                            title="Remove this action"
                                            class="w-6 h-6 flex-shrink-0 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">&times;</button>
                                </div>

                                {{-- When. Three modes over one payload shape;
                                     the resolved date is echoed back so the
                                     doctor sees 12 Aug, not "after 4 days". --}}
                                <div class="flex flex-wrap items-center gap-1 mb-1.5">
                                    <button type="button" @click="na.due_mode = 'tomorrow'"
                                            class="px-2 py-1 text-[11px] font-semibold border rounded-lg transition-colors"
                                            :class="na.due_mode === 'tomorrow'
                                                ? 'bg-[#6a0f70] border-[#380740] text-white'
                                                : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70]'">Tomorrow</button>
                                    <button type="button" @click="na.due_mode = 'in_days'"
                                            class="px-2 py-1 text-[11px] font-semibold border rounded-lg transition-colors"
                                            :class="na.due_mode === 'in_days'
                                                ? 'bg-[#6a0f70] border-[#380740] text-white'
                                                : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70]'">After X days</button>
                                    <button type="button" @click="na.due_mode = 'on_date'"
                                            class="px-2 py-1 text-[11px] font-semibold border rounded-lg transition-colors"
                                            :class="na.due_mode === 'on_date'
                                                ? 'bg-[#6a0f70] border-[#380740] text-white'
                                                : 'bg-white border-gray-200 text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70]'">Pick date</button>
                                </div>

                                <div x-show="na.due_mode === 'in_days'" x-cloak class="flex items-center gap-1.5 mb-1.5">
                                    <input type="number" min="1" max="365" x-model="na.due_in_days"
                                           class="w-16 text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                                    <span class="text-[11px] text-gray-500">days after this visit</span>
                                </div>

                                <div x-show="na.due_mode === 'on_date'" x-cloak class="mb-1.5">
                                    <input type="date" x-model="na.due_date"
                                           class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                                </div>

                                <textarea x-model="na.instruction" rows="2"
                                          placeholder="Instruction for staff — e.g. Ask whether she has any pain or discomfort after the procedure."
                                          class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]"></textarea>

                                <p class="text-[10px] text-gray-500 mt-1">
                                    Reception will see this on
                                    <span class="font-semibold text-[#6a0f70]" x-text="nextActionDueLabel(na)"></span>.
                                </p>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addNextAction()"
                            x-show="nextActions.length < 5"
                            class="w-full mt-2 px-3 py-1.5 text-[11px] font-semibold border border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                        + Next action for reception
                    </button>
                </div>

                {{-- CARD 6 — Today's Summary. Reuses the EXISTING footerSummary
                     getter untouched (same one driving the sticky footer) —
                     split only for DISPLAY on its existing " · " separators
                     so each fact reads as its own line, per spec ("present
                     existing computed values more clearly... do not
                     calculate anything new"). No new getters or state. --}}
                <div x-show="footerSummary" x-cloak class="bg-gray-50 border border-gray-200 rounded-lg shadow-sm px-4 py-3.5">
                    <div class="tv-section-legend">Today's Summary</div>
                    <ul class="space-y-1">
                        <template x-for="part in footerSummary.split(' · ')" :key="part">
                            <li class="flex items-center gap-1.5 text-xs text-gray-600">
                                <span class="w-1 h-1 rounded-full bg-gray-400 flex-shrink-0"></span>
                                <span x-text="part"></span>
                            </li>
                        </template>
                    </ul>
                </div>


                </div>

                </div>{{-- /two-column workspace --}}

                {{-- IA polish (visual zoning only) — Chapter 3 of 4. Same
                     x-show/x-cloak condition the small inline eyebrow used
                     before this sprint (only render when a worksheet-bearing
                     treatment is selected — never an empty heading with
                     nothing beneath it), just restyled to the same page-level
                     .tv-zone-heading treatment as the other three chapters,
                     with generous margin above so it reads as its own stage
                     of treatment rather than a continuation of Today's
                     Encounter. --}}
                <div x-show="['RCT','Implant','Filling','Scaling','Extraction','Crown Prep'].includes(form.treatment_name)" x-cloak class="mt-6">
                    <div class="tv-zone-heading">Procedure Worksheet</div>
                    <div class="tv-zone-divider"></div>
                </div>

                {{-- Procedure Worksheet -- full width, below the 3-column row.
                     Phase 4 (final polish): reuses the exact existing
                     expansion logic — a single x-show="form.treatment_name
                     === 'X'" per card, gated on the SAME one-value string,
                     so exactly one worksheet can ever be visible at a time.
                     Nothing about that logic changed. Only normalized:
                     eyebrow tracking to match the other section labels
                     (.07em → .08em), card vertical padding (py-5 → py-4)
                     and internal grid gap (gap-3 → gap-2.5) to match
                     Today's Procedures/Intelligence Panel rhythm, and
                     dropped the hover:shadow lift these six cards had that
                     no other card on the page uses (inconsistent shadow).
                     Note on Section Organization: per procedure, "Notes"
                     and "Completion" are intentionally NOT duplicated here
                     — this page already has one shared Clinical Notes hero
                     card and one shared Intelligence Panel Completion card;
                     adding worksheet-local copies would mean new Alpine
                     state and duplicate payload keys, which this sprint's
                     constraints rule out. Each worksheet below groups only
                     what it actually has: Clinical Details, and Materials/
                     Components where that data exists (Implant only —
                     Filling/Crown Prep have a single material-ish field
                     each, too small to warrant a separate labelled group). --}}
                <div class="space-y-5">

                {{-- RCT --}}
                <div x-show="form.treatment_name === 'RCT'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">RCT Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">No. of Canals</label>
                            <select x-model="form.rct_num_canals" @change="syncCanalRows()"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>1</option><option>2</option><option>3</option><option>4</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">File Type</label>
                            <select x-model="form.rct_file_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>K-file (Manual)</option>
                                <option>Rotary NiTi</option>
                                <option>Reciprocating</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Irrigant</label>
                            <select x-model="form.rct_irrigant"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>NaOCl 2.5%</option><option>NaOCl 5.25%</option>
                                <option>EDTA</option><option>CHX 2%</option><option>Saline</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Obturation Method</label>
                            <select x-model="form.rct_obturation_method"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Cold Lateral Condensation</option>
                                <option>Warm Vertical Compaction</option>
                                <option>Single Cone</option>
                            </select>
                        </div>
                    </div>
                    {{-- Canal lengths — its own labelled group, separated by a
                         divider so it doesn't read as a continuation of the
                         4-field grid above. --}}
                    <div x-show="form.rct_canal_lengths && form.rct_canal_lengths.length > 0" class="mt-3 pt-3 border-t border-gray-100">
                        <label class="text-xs font-semibold text-gray-600 block mb-2">Canal Lengths</label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="(canal, idx) in form.rct_canal_lengths" :key="idx">
                                <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-1.5">
                                    <select x-model="canal.name" class="text-xs border-0 outline-none bg-transparent font-semibold text-gray-600 w-16">
                                        <option>MB</option><option>DB</option><option>P</option>
                                        <option>MB2</option><option>ML</option><option>DL</option>
                                        <option>C1</option><option>C2</option>
                                    </select>
                                    <input type="text" x-model="canal.length" placeholder="e.g. 21mm"
                                           class="text-xs border-0 outline-none flex-1 text-gray-700">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Implant --}}
                <div x-show="form.treatment_name === 'Implant'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">Implant Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Implant Brand</label>
                            <select x-model="form.impl_brand"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Straumann</option><option>Nobel Biocare</option><option>Osstem</option>
                                <option>MIS</option><option>Neodent</option><option>Adin</option>
                                <option>Alpha Bio</option><option>BioHorizons</option><option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Size (Dia × Length)</label>
                            <input type="text" x-model="form.impl_size" placeholder="e.g. 4.0 × 10mm"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Insertion Torque</label>
                            <input type="text" x-model="form.impl_torque" placeholder="e.g. 35 Ncm"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Healing Collar</label>
                            <input type="text" x-model="form.impl_healing_collar" placeholder="Size / type"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Graft Used</label>
                            <select x-model="form.impl_graft_used"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">None</option>
                                <option>Autograft</option><option>Allograft</option>
                                <option>Xenograft</option><option>Alloplast</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Graft Brand</label>
                            <input type="text" x-model="form.impl_graft_brand" placeholder="e.g. Bio-Oss"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Membrane</label>
                            <input type="text" x-model="form.impl_membrane" placeholder="e.g. Bio-Gide, Collagen membrane"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>

                        {{-- Materials / Components — stock-linked, deducts real
                             inventory. Given its own labelled group + divider
                             (was an unlabelled border-top before). --}}
                        @php
                            $implFixtures    = ($implantCatalog ?? collect())->where('component_type', 'fixture');
                            $implAccessories = ($implantCatalog ?? collect())->where('component_type', '!=', 'fixture');
                        @endphp
                        <div class="col-span-2 border-t border-gray-100 pt-3 mt-1">
                            <p class="text-[10.5px] font-bold uppercase tracking-[.06em] text-gray-500 mb-2">Materials &amp; Components</p>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Fixture (from stock catalog)</label>
                            <select x-model="form.implant_fixture_catalog_id"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Not in catalog / use free-text Brand + Size above —</option>
                                @foreach($implFixtures as $fx)
                                    <option value="{{ $fx->id }}">
                                        {{ $fx->getFullName() }}@if($fx->inventoryItem) ({{ rtrim(rtrim(number_format($fx->inventoryItem->total_stock, 2), '0'), '.') }} in stock)@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">Selecting a fixture deducts it from stock automatically and creates a traceable placement record.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Lot / Batch Number</label>
                            <input type="text" x-model="form.implant_lot_number" placeholder="For traceability"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Components Used (deducted from stock)</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach($implAccessories as $acc)
                                    <label class="flex items-center gap-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 cursor-pointer">
                                        <input type="checkbox" value="{{ $acc->id }}" x-model="form.implant_components_used" class="rounded">
                                        <span>{{ $acc->getComponentTypeLabel() }} — {{ $acc->brand }}@if($acc->inventoryItem)<span class="text-gray-500"> ({{ rtrim(rtrim(number_format($acc->inventoryItem->total_stock, 2), '0'), '.') }})</span>@endif</span>
                                    </label>
                                @endforeach
                                @if($implAccessories->isEmpty())
                                    <span class="text-xs text-gray-500">No accessory components in the catalog yet — add them under Inventory → Implant Registry.</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Filling --}}
                <div x-show="form.treatment_name === 'Filling'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">Filling Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Material</label>
                            <select x-model="form.fill_material"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Composite</option><option>GIC</option>
                                <option>RMGIC</option><option>Amalgam</option>
                                <option>Compomer</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Shade</label>
                            <input type="text" x-model="form.fill_shade" placeholder="e.g. A2, B1"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>

                {{-- Scaling --}}
                <div x-show="form.treatment_name === 'Scaling'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">Scaling Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Quadrants</label>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                @foreach(['UR','UL','LR','LL','Full Mouth'] as $q)
                                <button type="button"
                                        @click="toggleQuadrant('{{ $q }}')"
                                        :class="quadrantSelected('{{ $q }}') ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200'"
                                        class="px-2.5 py-1 text-xs border rounded-full font-medium transition-colors">{{ $q }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Method</label>
                            <select x-model="form.scale_method"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Ultrasonic</option><option>Hand Instruments</option><option>Both</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Extraction --}}
                <div x-show="form.treatment_name === 'Extraction'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">Extraction Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Type</label>
                            <select x-model="form.ext_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Simple</option><option>Surgical</option><option>Impacted</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Socket Condition</label>
                            <select x-model="form.ext_socket"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Intact</option><option>Bone graft placed</option>
                                <option>Membrane placed</option><option>Irrigated</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 col-span-2">
                            <input type="checkbox" x-model="form.ext_suture" id="ext_suture_cb" class="rounded">
                            <label for="ext_suture_cb" class="text-xs font-semibold text-gray-600">Suture Placed</label>
                        </div>
                    </div>
                </div>

                {{-- Crown Prep --}}
                <div x-show="form.treatment_name === 'Crown Prep'" class="bg-white border border-gray-200 border-l-[3px] border-l-[#6a0f70] rounded-xl px-5 py-4 shadow-sm">
                    <div class="tv-section-legend">Crown Details</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Crown Type</label>
                            <select x-model="form.crown_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>PFM</option><option>Zirconia</option><option>Full Metal</option>
                                <option>Emax</option><option>PFZ</option><option>PEEK</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Shade</label>
                            <input type="text" x-model="form.crown_shade" placeholder="e.g. A2, B1"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Temporary Crown</label>
                            <input type="text" x-model="form.crown_temp_placed" placeholder="Material / brand"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="flex items-center gap-2 mt-4">
                            <input type="checkbox" x-model="form.crown_impression" id="crown_imp_cb" class="rounded">
                            <label for="crown_imp_cb" class="text-xs font-semibold text-gray-600">Impression Taken</label>
                        </div>
                    </div>
                </div>

                </div>{{-- /procedure worksheets --}}

                {{-- IA polish (visual zoning only) — Chapter 4 of 4. The
                     sticky footer just below (Cancel / live summary / Save)
                     is already visually separated by its own fixed
                     positioning, border-top and shadow — this heading is the
                     in-flow cue that appears right as the doctor finishes
                     scrolling, marking "everything above this line is the
                     visit; this is where it gets committed." No change to
                     the footer itself — same markup, same saveVisit()
                     binding, same dusk selector. --}}
                <div class="mt-6">
                    <div class="tv-zone-heading">Complete Visit</div>
                    <div class="tv-zone-divider" style="margin-bottom:8px;"></div>
                    <p class="text-xs text-gray-500 mb-2">Review the summary below, then Save to commit this visit.</p>
                </div>

            </div>{{-- /clinical workspace (space-y-6) --}}

        </div>{{-- end body --}}


        {{-- Footer — Delta 2: live micro-summary derived from existing state,
             sitting beside Save so the last thing read is what gets committed.
             .tv-sticky-footer (defined in this file's own style block, 08-05
             follow-up) uses position:fixed anchored to the sidebar's real
             current width, since plain sticky bottom-0 wasn't holding against
             this app's nested scroll shell. --}}

        <div class="tv-sticky-footer border-t border-gray-200 bg-white/95 backdrop-blur-sm shadow-[0_-2px_8px_rgba(0,0,0,0.04)]">
            <div class="max-w-[1440px] mx-auto flex items-center justify-between gap-3 px-6 py-3.5">
                <a href="{{ route('patients.show', $patient) }}#visits" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 font-medium flex-shrink-0">
                    Cancel
                </a>
                <span class="text-[11px] text-gray-500 text-right ml-auto max-sm:hidden" x-text="footerSummary"></span>
                <button @click="saveVisit()" :disabled="saving" dusk="visit-save"
                        class="inline-flex items-center gap-2 bg-[#6a0f70] hover:bg-[#570c5d] disabled:opacity-60 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                    <svg x-show="saving" class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
                        <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                        <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
                        <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
                    </svg>
                    <span x-text="saving ? 'Saving…' : (editingVisit ? 'Update Visit' : 'Save Visit')"></span>
                </button>
            </div>
        </div>
