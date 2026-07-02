@extends('layouts.app')
@section('page-title', 'AOCP Membership Tiers')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    <a href="{{ route('finance.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-[#6a0f70] mb-4">← Finance</a>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">AOCP Membership Tiers</h1>
            <p class="text-sm text-gray-500 mt-0.5">Define plans and their benefits. Patients enroll from their billing profile.</p>
        </div>
        <a href="{{ route('finance.membership.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
            + New Tier
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Dashboard Stats (4 cards — no Inactive) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $totalPlans }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Plans</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $activePlans }}</p>
            <p class="text-xs text-gray-500 mt-1">Active Plans</p>
        </div>
        {{-- Clickable Active Members card --}}
        <button onclick="openMembersPanel()"
                class="bg-white rounded-xl border border-indigo-300 p-4 text-center hover:bg-indigo-50 hover:border-indigo-400 transition cursor-pointer group">
            <p id="activeMemberCount" class="text-2xl font-bold text-indigo-600 group-hover:text-indigo-800">{{ $totalActiveMembers }}</p>
            <p class="text-xs text-gray-500 mt-1">Active Members</p>
            <p class="text-[10px] text-indigo-400 mt-0.5">click to view list →</p>
        </button>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold {{ $membershipRevenue > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                Rs. {{ number_format($membershipRevenue, 0) }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Revenue (FY)</p>
        </div>
    </div>

    {{-- Plans table --}}
    @if($plans->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-dashed border-gray-300">
            <p class="text-gray-400 text-sm">No membership tiers defined yet.</p>
            <a href="{{ route('finance.membership.create') }}"
               class="mt-3 inline-block text-indigo-600 text-sm font-medium hover:underline">
                Create your first tier →
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Plan</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Price</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Duration</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Benefits</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($plans as $plan)
                    <tr class="hover:bg-gray-50 transition" id="row-{{ $plan->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800">{{ $plan->plan_name }}</p>
                            @if($plan->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $plan->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-700">Rs. {{ number_format($plan->price, 0) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $plan->duration_label }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs max-w-xs">{{ $plan->benefit_summary }}</td>
                        <td class="px-4 py-3">
                            <button onclick="togglePlan({{ $plan->id }}, this)"
                                    data-active="{{ $plan->is_active ? '1' : '0' }}"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium transition
                                           {{ $plan->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('finance.membership.edit', $plan) }}"
                               class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>
                            <form method="POST" action="{{ route('finance.membership.destroy', $plan) }}"
                                  class="inline"
                                  onsubmit="return confirm('Delete this tier? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

{{-- ============================================================
     Active Members Full-Screen Overlay
     ============================================================ --}}
<div id="membersPanel"
     class="fixed inset-0 bg-white z-50 hidden flex flex-col">

    {{-- Panel header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-indigo-50">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Active Members</h2>
            <p id="memberCount" class="text-xs text-gray-500 mt-0.5">Loading…</p>
        </div>
        <button onclick="closeMembersPanel()"
                class="text-gray-400 hover:text-gray-700 text-2xl leading-none font-light">&times;</button>
    </div>

    {{-- Filters bar --}}
    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-[10px] text-gray-500 font-medium uppercase tracking-wide block mb-1">Sort by</label>
            <select id="filterSort" onchange="applyFilters()"
                    class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                <option value="name_asc">Name A→Z</option>
                <option value="expiry_asc">Expiry: oldest first</option>
                <option value="joining_asc">Joined: earliest first</option>
                <option value="last_visit_asc">Last visit: oldest first</option>
            </select>
        </div>
        <div>
            <label class="text-[10px] text-gray-500 font-medium uppercase tracking-wide block mb-1">Gender</label>
            <select id="filterGender" onchange="applyFilters()"
                    class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div>
            <label class="text-[10px] text-gray-500 font-medium uppercase tracking-wide block mb-1">Age group</label>
            <select id="filterAge" onchange="applyFilters()"
                    class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All</option>
                <option value="0-17">0–17</option>
                <option value="18-35">18–35</option>
                <option value="36-55">36–55</option>
                <option value="56+">56+</option>
            </select>
        </div>
        <button onclick="resetFilters()"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50">
            Reset
        </button>
    </div>

    {{-- Member list --}}
    <div id="memberListWrapper" class="flex-1 overflow-y-auto px-6 py-4">
        <div id="memberListLoading" class="py-16 text-center text-sm text-gray-400">Loading members…</div>
        <div id="memberListEmpty" class="py-16 text-center text-sm text-gray-400 hidden">No members match the current filters.</div>
        <div class="hidden bg-white rounded-xl border border-gray-200 overflow-hidden" id="memberTableWrap">
            <table class="w-full text-sm" id="memberTable">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Plan</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Joined</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Expires</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Last Visit</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody id="memberTableBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const MEMBERS_URL     = '{{ route("finance.membership.members") }}';
const ENROLL_DEL_BASE = '{{ url("finance/membership/enrollment") }}';

let allMembers = [];

// ── Open / close ──────────────────────────────────────────────
function openMembersPanel() {
    document.getElementById('membersPanel').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (allMembers.length === 0) loadMembers();
}
function closeMembersPanel() {
    document.getElementById('membersPanel').classList.add('hidden');
    document.body.style.overflow = '';
}

// ── Fetch ─────────────────────────────────────────────────────
function loadMembers() {
    fetch(MEMBERS_URL, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            allMembers = data;
            document.getElementById('memberListLoading').classList.add('hidden');
            applyFilters();
        })
        .catch(() => {
            document.getElementById('memberListLoading').textContent = 'Failed to load members.';
        });
}

// ── Filter + sort ─────────────────────────────────────────────
function applyFilters() {
    const sort   = document.getElementById('filterSort').value;
    const gender = document.getElementById('filterGender').value.toLowerCase();
    const age    = document.getElementById('filterAge').value;

    let filtered = [...allMembers];

    if (gender) {
        filtered = filtered.filter(m => (m.gender || '').toLowerCase() === gender);
    }
    if (age) {
        filtered = filtered.filter(m => {
            const a = m.age;
            if (a === null || a === undefined) return false;
            if (age === '0-17')  return a <= 17;
            if (age === '18-35') return a >= 18 && a <= 35;
            if (age === '36-55') return a >= 36 && a <= 55;
            if (age === '56+')   return a >= 56;
            return true;
        });
    }

    // Sort
    filtered.sort((a, b) => {
        if (sort === 'name_asc')       return (a.name || '').localeCompare(b.name || '');
        if (sort === 'expiry_asc')     return (a.end_date || '').localeCompare(b.end_date || '');
        if (sort === 'joining_asc')    return (a.start_date || '').localeCompare(b.start_date || '');
        if (sort === 'last_visit_asc') return (a.last_visit || '9999').localeCompare(b.last_visit || '9999');
        return 0;
    });

    renderMembers(filtered);
}

function resetFilters() {
    document.getElementById('filterSort').value   = 'name_asc';
    document.getElementById('filterGender').value = '';
    document.getElementById('filterAge').value    = '';
    applyFilters();
}

// ── Render ────────────────────────────────────────────────────
function renderMembers(list) {
    const wrap   = document.getElementById('memberTableWrap');
    const empty  = document.getElementById('memberListEmpty');
    const tbody  = document.getElementById('memberTableBody');
    const count  = document.getElementById('memberCount');

    count.textContent = `${list.length} member${list.length !== 1 ? 's' : ''}`;

    if (list.length === 0) {
        wrap.classList.add('hidden');
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');
    wrap.classList.remove('hidden');

    tbody.innerHTML = list.map(m => `
        <tr class="hover:bg-gray-50 transition" id="mrow-${m.enrollment_id}">
            <td class="px-4 py-2.5">
                <p class="font-medium text-gray-800">${esc(m.name)}</p>
                <p class="text-xs text-gray-400">${esc(m.age_label)} · ${esc(capitalize(m.gender))}</p>
            </td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${esc(m.plan_name)}</td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${esc(m.start_date_fmt)}</td>
            <td class="px-4 py-2.5 text-xs">
                <span class="${isExpiringSoon(m.end_date) ? 'text-amber-600 font-medium' : 'text-gray-600'}">${esc(m.end_date_fmt)}</span>
            </td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${esc(m.last_visit_fmt)}</td>
            <td class="px-4 py-2.5 text-right relative">
                <button onclick="toggleMemberMenu(event, ${m.enrollment_id})"
                        class="text-gray-400 hover:text-gray-700 px-2 py-1 rounded hover:bg-gray-100 transition text-lg leading-none">⋮</button>
                <div id="mmenu-${m.enrollment_id}"
                     class="hidden absolute right-4 top-8 z-50 bg-white border border-gray-200 rounded-lg shadow-lg min-w-[130px] py-1">
                    <button onclick="cancelEnrollment(${m.enrollment_id}, this)"
                            class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Remove
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ── Three-dot menu ────────────────────────────────────────────
function toggleMemberMenu(event, id) {
    event.stopPropagation();
    // Close any other open menus
    document.querySelectorAll('[id^="mmenu-"]').forEach(el => {
        if (el.id !== `mmenu-${id}`) el.classList.add('hidden');
    });
    document.getElementById(`mmenu-${id}`)?.classList.toggle('hidden');
}
document.addEventListener('click', () => {
    document.querySelectorAll('[id^="mmenu-"]').forEach(el => el.classList.add('hidden'));
});

// ── Delete enrollment ─────────────────────────────────────────
function cancelEnrollment(id, btn) {
    if (!confirm('Remove this patient\'s membership? No refund will be issued.')) return;

    btn.disabled = true;
    btn.textContent = '…';

    fetch(`${ENROLL_DEL_BASE}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove from local cache + re-render
            allMembers = allMembers.filter(m => m.enrollment_id !== id);
            applyFilters();
            // Update the stat card count
            const card = document.getElementById('activeMemberCount');
            if (card) card.textContent = Math.max(0, parseInt(card.textContent) - 1);
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Remove'; });
}

// ── Helpers ───────────────────────────────────────────────────
function esc(s) {
    if (!s) return '—';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function isExpiringSoon(dateStr) {
    if (!dateStr) return false;
    const diff = (new Date(dateStr) - new Date()) / 86400000;
    return diff >= 0 && diff <= 30;
}

// ── Plan toggle ───────────────────────────────────────────────
function togglePlan(id, btn) {
    fetch(`/finance/membership/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = data.label;
        btn.dataset.active = data.is_active ? '1' : '0';
        btn.className = 'px-2.5 py-1 rounded-full text-xs font-medium transition ' +
            (data.is_active
                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                : 'bg-gray-100 text-gray-500 hover:bg-gray-200');
    });
}
</script>
@endsection
