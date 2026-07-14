{{--
    Communication OS — Sidebar Navigation
    Dentfluence · Tulip Dental
--}}
<aside class="comm-sidebar" id="comm-sidebar" role="navigation" aria-label="Communication OS navigation">

    {{-- ── Module Brand ────────────────────────────────────────────── --}}
    <div class="comm-sidebar__brand">
        <div class="comm-sidebar__brand-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div class="comm-sidebar__brand-text">
            <span class="comm-sidebar__brand-name">Communication OS</span>
            <span class="comm-sidebar__brand-sub">Dentfluence · Tulip Dental</span>
        </div>
        <button class="comm-sidebar__collapse-btn" id="sidebar-collapse-btn"
                aria-label="Collapse sidebar" title="Collapse sidebar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
        </button>
    </div>

    {{-- ── Nav Items ───────────────────────────────────────────────── --}}
    <nav class="comm-sidebar__nav">
        <ul class="comm-sidebar__nav-list" role="list">

            {{-- Dashboard --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('communication.index') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'dashboard' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'dashboard' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Dashboard</span>
                </a>
            </li>

            <li class="comm-sidebar__nav-divider" role="separator">
                <span>Execution</span>
            </li>

            {{-- Today's Calls — was "Communication Manager", pointing at the retired
                 PRM-style list screen. Renamed + repointed to PRE's Today's Actions
                 2026-07-06 (Sumit's call). --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.today') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'manager' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'manager' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Today's Calls</span>
                    @if(($navBadges['overdue_count'] ?? 0) > 0)
                        <span class="comm-sidebar__nav-badge comm-sidebar__nav-badge--urgent"
                              aria-label="{{ $navBadges['overdue_count'] }} overdue">
                            {{ $navBadges['overdue_count'] }}
                        </span>
                    @endif
                </a>
            </li>

            {{-- Lead Pipeline (PRE) — label fixed 2026-07-06; this has been the PRE
                 lead pipeline since Phase 8 retired the old PRM board, it just kept
                 the old "PRM" name tag until now. $activeNav key left as 'prm' so
                 any controller already passing activeNav('prm') still highlights it. --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.pipeline') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'prm' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'prm' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="5" height="8"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Lead Pipeline</span>
                </a>
            </li>

            {{-- Follow-up Engine --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('communication.followup.index') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'followup' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'followup' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Follow-up Engine</span>
                    @if(($navBadges['followup_overdue_count'] ?? 0) > 0)
                        <span class="comm-sidebar__nav-badge comm-sidebar__nav-badge--urgent"
                              aria-label="{{ $navBadges['followup_overdue_count'] }} overdue">
                            {{ $navBadges['followup_overdue_count'] }}
                        </span>
                    @endif
                </a>
            </li>

            {{-- Recall Engine (2026-07-05 — was previously reachable by URL only, no nav link) --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('communication.recall.index') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'recall' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'recall' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 2v6h6"/><path d="M3 13a9 9 0 1 0 3-6.7L3 8"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Recall Engine</span>
                </a>
            </li>

            <li class="comm-sidebar__nav-divider" role="separator">
                <span>Intelligence</span>
            </li>

            {{-- Opportunity Engine — retired 2026-07-06, now the PRE Opportunity Pipeline --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.opportunities') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'opportunities' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'opportunities' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Opportunity Engine</span>
                </a>
            </li>

            {{-- Tasks & Assignments --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('communication.tasks.index') }}"
                   class="comm-sidebar__nav-link {{ $activeNav === 'tasks' ? 'is-active' : '' }}"
                   aria-current="{{ $activeNav === 'tasks' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="9" y1="11" x2="9" y2="17"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="15" y1="11" x2="15" y2="17"/><path d="M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="9 4 9 2 15 2 15 4"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Tasks & Assignments</span>
                    @if(($navBadges['pending_tasks_count'] ?? 0) > 0)
                        <span class="comm-sidebar__nav-badge"
                              aria-label="{{ $navBadges['pending_tasks_count'] }} pending">
                            {{ $navBadges['pending_tasks_count'] }}
                        </span>
                    @endif
                </a>
            </li>

            <li class="comm-sidebar__nav-divider" role="separator">
                <span>Relationships</span>
            </li>

            {{-- Today's Actions --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.today') }}"
                   class="comm-sidebar__nav-link {{ ($activeNav ?? '') === 'relationship_today' ? 'is-active' : '' }}"
                   aria-current="{{ ($activeNav ?? '') === 'relationship_today' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Today's Actions</span>
                </a>
            </li>

            {{-- Relationships (search/list) --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.search') }}"
                   class="comm-sidebar__nav-link {{ ($activeNav ?? '') === 'relationships' ? 'is-active' : '' }}"
                   aria-current="{{ ($activeNav ?? '') === 'relationships' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Relationships</span>
                </a>
            </li>

            {{-- Relationship Analytics --}}
            <li class="comm-sidebar__nav-item">
                <a href="{{ route('relationship.analytics') }}"
                   class="comm-sidebar__nav-link {{ ($activeNav ?? '') === 'relationship_analytics' ? 'is-active' : '' }}"
                   aria-current="{{ ($activeNav ?? '') === 'relationship_analytics' ? 'page' : 'false' }}">
                    <span class="comm-sidebar__nav-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </span>
                    <span class="comm-sidebar__nav-label">Analytics</span>
                </a>
            </li>

            {{-- 'History' divider + Communication Timeline nav removed
                 2026-07-14 (production hardening) — TimelineController still
                 renders hardcoded SAMPLE patients (getDummyPatients). Restore
                 together with the config/communication.php tile once the
                 controller is wired to live data. --}}

            {{-- Templates nav item removed 2026-07-06 — Templates moved to the
                 Relationship/PRE module (relationship.templates.*). It's now a
                 deep-link-only destination (reached via Settings gear icons),
                 not a standalone Communication OS nav item. --}}

        </ul>
    </nav>

    {{-- ── Back to Dentfluence ─────────────────────────────────────── --}}
    <div class="comm-sidebar__footer">
        <a href="{{ route('dashboard') }}" class="comm-sidebar__back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <span>Back to Dentfluence</span>
        </a>
    </div>

</aside>
