{{-- ══════════════════════════════════════════════════════════
     MEMBERSHIP TAB
     Shows: active plan card · family members · renewal dates
            benefits availed log · past membership history
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'membership'" style="display:none" class="w-full px-6 py-5">

    @php
        /** @var \App\Models\Finance\FinancePatientMembership|null $activeMembership */
        /** @var \Illuminate\Support\Collection $membershipHistory */
        /** @var \Illuminate\Support\Collection $benefitLogs */

        $hasMembership = !is_null($activeMembership) && $activeMembership->isActive();

        // Colour helpers
        $statusColor = match(true) {
            $hasMembership                                         => 'green',
            ($activeMembership && !$activeMembership->isActive())  => 'red',
            default                                                => 'gray',
        };
        $statusLabel = match(true) {
            $hasMembership                                         => 'Active',
            ($activeMembership && !$activeMembership->isActive())  => 'Expired',
            default                                                => 'Not Enrolled',
        };
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- ── 1. Active Plan Card ───────────────────────────────────────── --}}
        @if($hasMembership)
            @php
                $plan     = $activeMembership->plan;
                $benefits = $plan ? $plan->getBenefitList() : [];
                $isAddon  = $activeMembership->isAddon();

                // Family members linked to this enrollment — any non-add-on member
                // can have add-ons attached (no "head" concept anymore).
                $familyMembers = !$isAddon
                    ? $activeMembership->familyMembers()->with('patient')->get()
                    : collect();

                // If addon — load head info
                $headEnrollment = $isAddon
                    ? $activeMembership->familyHead()->with('patient')->first()
                    : null;

                $daysLeft = $activeMembership->days_remaining;
                $expiringSoon = $daysLeft <= 30 && $daysLeft > 0;

                // Flag backdated entries: start date is earlier than the day the record was created.
                $wasBackdated = $activeMembership->created_at
                    && $activeMembership->start_date->lt($activeMembership->created_at->copy()->startOfDay());
            @endphp

            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                {{-- Header bar --}}
                <div class="bg-[#6a0f70] px-5 py-4 flex items-center justify-between">
                    <div>
                        <div class="text-white text-xs uppercase tracking-wider opacity-75 mb-0.5">Active Membership</div>
                        <div class="text-white text-lg font-bold">{{ $plan?->plan_name ?? 'Membership Plan' }}</div>
                        @if($activeMembership->family_display_name)
                            <div class="text-purple-200 text-xs mt-0.5">{{ $activeMembership->family_display_name }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-white text-xs opacity-75">Renews / Expires</div>
                        <div class="text-white font-semibold text-sm">
                            {{ $activeMembership->end_date->format('d M Y') }}
                        </div>
                        <div class="mt-1">
                            @if($expiringSoon)
                                <span class="text-xs bg-yellow-400 text-yellow-900 font-semibold px-2 py-0.5 rounded-full">
                                    {{ $daysLeft }} days left
                                </span>
                            @else
                                <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full">
                                    {{ $daysLeft }} days left
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Plan details grid --}}
                <div class="px-5 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4 border-b border-gray-100">
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Plan Type</div>
                        <div class="text-sm font-medium text-gray-700">{{ $plan?->duration_label ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1 flex items-center gap-1">
                            Validity
                            @if($wasBackdated)
                                <span class="normal-case tracking-normal text-[10px] bg-amber-100 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full font-semibold"
                                      title="Backdated entry — recorded on {{ $activeMembership->created_at->format('d M Y') }}">Backdated</span>
                            @endif
                        </div>
                        <div class="text-sm font-medium text-gray-700">
                            {{ $activeMembership->start_date->format('d M Y') }}
                            <span class="text-gray-400">→</span>
                            {{ $activeMembership->end_date->format('d M Y') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Member Type</div>
                        <div class="text-sm font-medium text-gray-700">
                            @if($isAddon)
                                <span class="text-blue-600 font-semibold">Family Add-on</span>
                            @elseif($familyMembers->isNotEmpty())
                                <span class="text-purple-700 font-semibold">Family</span>
                            @else
                                Individual
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Amount Paid</div>
                        <div class="text-sm font-medium text-gray-700">Rs. {{ number_format($activeMembership->amount_paid, 0) }}</div>
                    </div>
                </div>

                {{-- Benefits included in plan --}}
                @if($plan)
                    <div class="px-5 py-4 border-b border-gray-100">
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2 font-medium">Plan Benefits</div>
                        <div class="flex flex-wrap gap-2">
                            @if($benefits['free_consultation'] ?? false)
                                <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full">✓ Free Consultation</span>
                            @endif
                            @if($benefits['free_xray'] ?? false)
                                <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-full">✓ Free X-Ray</span>
                            @endif
                            @if($benefits['free_scaling'] ?? false)
                                <span class="text-xs bg-teal-50 text-teal-700 border border-teal-200 px-2.5 py-1 rounded-full">✓ Free Scaling</span>
                            @endif
                            @if(($benefits['discount_percent'] ?? 0) > 0)
                                <span class="text-xs bg-purple-50 text-purple-700 border border-purple-200 px-2.5 py-1 rounded-full">✓ {{ $benefits['discount_percent'] }}% Off All Treatments</span>
                            @endif
                            @foreach(($benefits['free_treatments'] ?? []) as $ft)
                                <span class="text-xs bg-green-50 text-green-700 border border-green-200 px-2.5 py-1 rounded-full">✓ Free {{ $ft }}</span>
                            @endforeach
                            @if(empty(array_filter([$benefits['free_consultation'] ?? false, $benefits['free_xray'] ?? false, $benefits['free_scaling'] ?? false, ($benefits['discount_percent'] ?? 0) > 0])) && empty($benefits['free_treatments'] ?? []))
                                <span class="text-xs text-gray-400">No specific benefits configured for this plan.</span>
                            @endif
                        </div>
                        @if(!empty($benefits['notes']))
                            <div class="mt-2 text-xs text-gray-500 bg-gray-50 rounded px-3 py-1.5">
                                {{ $benefits['notes'] }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- If addon — show head info --}}
                @if($isAddon && $headEnrollment)
                    <div class="px-5 py-3 bg-blue-50 border-b border-blue-100">
                        <div class="text-xs text-blue-600 font-medium mb-1">Family Group</div>
                        <div class="text-sm text-blue-800">
                            Add-on under <strong>{{ $headEnrollment->patient?->name ?? 'Unknown' }}</strong>
                            ({{ $headEnrollment->family_name ?? 'Family' }})
                            · Expires {{ $headEnrollment->end_date->format('d M Y') }}
                        </div>
                    </div>
                @endif

                {{-- Show linked family members for any non-add-on member --}}
                @if(!$isAddon && $familyMembers->isNotEmpty())
                    <div class="px-5 py-4">
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2 font-medium">
                            Family Members
                            @if($familyMembers->isNotEmpty())
                                <span class="text-purple-600 font-semibold">({{ $familyMembers->count() }})</span>
                            @endif
                        </div>
                        @if($familyMembers->isEmpty())
                            <p class="text-xs text-gray-400">No family members added to this membership.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($familyMembers as $fm)
                                    <div class="flex items-center justify-between bg-purple-50 rounded-lg px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-purple-200 flex items-center justify-center text-xs text-purple-700 font-bold">
                                                {{ strtoupper(substr($fm->patient?->name ?? '?', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-700">{{ $fm->patient?->name ?? 'Unknown' }}</div>
                                                <div class="text-xs text-gray-400">Add-on member</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">Expires</div>
                                            <div class="text-xs font-semibold {{ $fm->isActive() ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $fm->end_date->format('d M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

            </div>

        @else
            {{-- No active membership --}}
            <div class="bg-white border border-gray-200 rounded-xl px-6 py-10 text-center">
                <div class="text-4xl mb-3"></div>
                @if($activeMembership && !$activeMembership->isActive())
                    <div class="text-sm font-semibold text-red-600 mb-1">Membership Expired</div>
                    <div class="text-xs text-gray-400">Expired on {{ $activeMembership->end_date->format('d M Y') }}</div>
                @else
                    <div class="text-sm font-semibold text-gray-600 mb-1">No Active Membership</div>
                    <div class="text-xs text-gray-400">Enroll this patient from the <strong>Billing tab → Membership section</strong>.</div>
                @endif
            </div>
        @endif

        {{-- ── 2. Benefits Availed Log ───────────────────────────────────── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Benefits Availed</h3>
                @if($benefitLogs->isNotEmpty())
                    <span class="text-xs text-gray-400">Last {{ $benefitLogs->count() }} entries</span>
                @endif
            </div>

            @if($benefitLogs->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-400">
                    No benefits availed yet. They will appear here when applied on invoices.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Benefit</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 hidden sm:table-cell">Plan</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 hidden sm:table-cell">Invoice</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-green-600">Saved</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($benefitLogs as $log)
                            @php
                                $badgeColors = [
                                    'blue'   => 'bg-blue-100 text-blue-700',
                                    'indigo' => 'bg-indigo-100 text-indigo-700',
                                    'teal'   => 'bg-teal-100 text-teal-700',
                                    'green'  => 'bg-green-100 text-green-700',
                                    'purple' => 'bg-purple-100 text-purple-700',
                                    'gray'   => 'bg-gray-100 text-gray-600',
                                ];
                                $colorClass = $badgeColors[$log->badge_color] ?? $badgeColors['gray'];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    {{ $log->availed_at->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $colorClass }}">
                                        {{ $log->benefit_type_label }}
                                    </span>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $log->benefit_label }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 hidden sm:table-cell">
                                    {{ $log->membership?->plan?->plan_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs hidden sm:table-cell">
                                    @if($log->invoice)
                                        <a href="{{ route('billing.invoice.show', $log->invoice) }}"
                                           class="text-purple-600 hover:underline font-medium">
                                            #{{ $log->invoice->invoice_number ?? $log->invoice_id }}
                                        </a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    @if($log->amount_saved > 0)
                                        <span class="font-semibold text-green-600">Rs. {{ number_format($log->amount_saved, 0) }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── 3. Membership History ─────────────────────────────────────── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Membership History</h3>
            </div>

            @if($membershipHistory->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-400">
                    No membership records found.
                </div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($membershipHistory as $hist)
                        @php
                            $isActive  = $hist->isActive();
                            $rowBg     = $isActive ? 'bg-green-50' : '';
                        @endphp
                        <div class="px-5 py-3 {{ $rowBg }} flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                {{-- Status dot --}}
                                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $isActive ? 'bg-green-500' : ($hist->status === 'expired' ? 'bg-red-400' : 'bg-gray-300') }}"></div>
                                <div>
                                    <div class="text-sm font-medium text-gray-700">
                                        {{ $hist->plan?->plan_name ?? 'Unknown Plan' }}
                                        @if($hist->member_type !== 'individual')
                                            <span class="text-xs text-purple-600 ml-1">({{ ucfirst($hist->member_type) }})</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ $hist->start_date->format('d M Y') }}
                                        →
                                        {{ $hist->end_date->format('d M Y') }}
                                        @if($hist->family_name)
                                            · {{ $hist->family_name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0 ml-4">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                    {{ $isActive
                                        ? 'bg-green-100 text-green-700'
                                        : ($hist->status === 'expired'
                                            ? 'bg-red-100 text-red-600'
                                            : 'bg-gray-100 text-gray-500') }}">
                                    {{ ucfirst($hist->status) }}
                                </span>
                                <div class="text-xs text-gray-400 mt-1">
                                    Rs. {{ number_format($hist->amount_paid, 0) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>


        {{-- ── 4. Action Buttons ────────────────────────────────── --}}
        @if(($membershipPlans ?? collect())->isNotEmpty())
        <div class="flex gap-3">
            @if($hasMembership)
                <button onclick="document.getElementById('enrollModal').classList.remove('hidden')"
                        class="flex-1 text-center text-sm font-semibold bg-[#6a0f70] text-white rounded-xl px-5 py-3 hover:bg-[#5a0c60] transition shadow-sm">
                    Renew / Change Plan
                </button>
                <button onclick="document.getElementById('enrollModal').classList.remove('hidden')"
                        class="flex-none text-sm font-medium border border-[#6a0f70] text-[#6a0f70] rounded-xl px-5 py-3 hover:bg-purple-50 transition">
                    + Add Family Member
                </button>
            @else
                <button onclick="document.getElementById('enrollModal').classList.remove('hidden')"
                        class="flex-1 text-center text-sm font-semibold bg-[#6a0f70] text-white rounded-xl px-5 py-3 hover:bg-[#5a0c60] transition shadow-sm">
                    Enroll in AOCP Membership
                </button>
            @endif
        </div>
        @endif

    </div>
</div>{{-- /membership tab --}}

{{-- Enroll / Renew Modal --}}
@if(($membershipPlans ?? collect())->isNotEmpty())
@php
    $familyHeadsList = $activeFamilyHeads ?? collect();
    $planFamilyData  = ($membershipPlans ?? collect())->mapWithKeys(fn($p) => [
        $p->id => [
            'family_option'      => $p->family_option,
            'addon_price'        => (float) ($p->addon_price ?? 0),
            'price'              => (float) $p->price,
            'max_family_members' => $p->max_family_members ?? 4,
        ]
    ])->toJson();
@endphp

<div id="enrollModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">
                {{ ($activeMembership && $activeMembership->isActive()) ? 'Renew / Change AOCP Plan' : 'Enroll in AOCP Membership' }}
            </h3>
            <button onclick="document.getElementById('enrollModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5">
            <form method="POST" action="{{ route('billing.membership.enroll', $patient) }}" id="enrollForm">
                @csrf
                {{-- member_type is derived server-side: "addon" if a member is linked, else "individual". --}}

                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Select Plan</p>
                <div class="space-y-2 mb-5">
                    @foreach($membershipPlans as $plan)
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 transition has-[:checked]:border-[#6a0f70] has-[:checked]:bg-purple-50">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}"
                               data-price="{{ $plan->price }}"
                               data-addon-price="{{ $plan->addon_price ?? '' }}"
                               data-family-option="{{ $plan->family_option }}"
                               class="mt-0.5 text-purple-700 enroll-plan-radio"
                               {{ $loop->first ? 'checked' : '' }}>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-800">{{ $plan->plan_name }}</p>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-[#6a0f70]">Rs. {{ number_format($plan->price, 0) }}</p>
                                    @if($plan->isAddonModel())
                                        <p class="text-[10px] text-emerald-600">+Rs. {{ number_format($plan->addon_price, 0) }}/add-on</p>
                                    @elseif($plan->isBundleModel())
                                        <p class="text-[10px] text-emerald-600">Bundle up to {{ $plan->max_family_members }} members</p>
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $plan->duration_label }} · {{ $plan->benefit_summary }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>

                <div id="familySection" class="hidden mb-4 p-3 bg-purple-50 border border-purple-200 rounded-xl">
                    <p class="text-xs font-semibold text-purple-700 mb-2">Family Enrollment</p>

                    {{-- Link to an existing AOCP member (optional).
                         · Pick someone → this patient is enrolled as an add-on under that member.
                         · Leave blank → enrolled as a standalone member.
                         No "family head" concept — any active member can be linked to. --}}
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Link to existing family member <span class="text-gray-400">(optional)</span>
                        </label>
                        @if($familyHeadsList->isNotEmpty())
                            <select name="family_head_membership_id" id="familyHeadSelect"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none">
                                <option value="">— None · enroll as new member —</option>
                                @foreach($familyHeadsList as $fh)
                                <option value="{{ $fh->id }}">
                                    {{ $fh->patient?->name ?? '?' }} · {{ $fh->plan?->plan_name ?? '' }}
                                    @if($fh->family_name) · {{ $fh->family_name }} @endif
                                    · exp {{ $fh->end_date->format('d M Y') }}
                                </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">
                                Pick a member to add this patient as an <strong>add-on</strong> to their family
                                <span id="addonPriceHint" class="text-emerald-600"></span>.
                                Leave blank to enroll as a standalone member.
                            </p>
                        @else
                            <p class="text-xs text-gray-500 bg-white border border-gray-200 rounded px-3 py-2">
                                No other active AOCP members yet — this patient will be enrolled as a new member.
                            </p>
                        @endif
                    </div>

                    <div id="familyNameRow" class="mb-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Family name <span class="text-gray-400">(optional)</span>
                        </label>
                        <input type="text" name="family_name" maxlength="100"
                               placeholder="e.g. Firke Family"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none">
                    </div>
                </div>

                {{-- Enrollment date — leave as today for normal entry, or set a past date for backdated enrollment --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Enrollment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="start_date" id="enrollStartDate"
                           value="{{ now()->toDateString() }}"
                           max="{{ now()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none">
                    <p class="text-[11px] text-gray-400 mt-1">Defaults to today. Set a past date for a backdated entry — validity and receipt will use this date.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Amount Collected (Rs.) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="amount_paid" id="enrollAmountPaid"
                           step="1" min="0"
                           value="{{ ($membershipPlans ?? collect())->first()?->price ?? '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none">
                    <p class="text-[11px] text-gray-400 mt-1" id="enrollAmountHint">Auto-filled from plan price.</p>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Payment Mode <span class="text-red-500">*</span>
                    </label>
                    <select name="payment_mode"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="netbanking">Net Banking</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div class="mb-5 flex items-start gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
                    <input type="checkbox" name="collect_now" id="enrollCollectNow" value="1"
                           class="mt-0.5 rounded border-gray-300 text-[#6a0f70] focus:ring-purple-400">
                    <label for="enrollCollectNow" class="text-xs text-gray-600 leading-snug">
                        <span class="font-medium text-gray-700">Collected now</span> — tick this only if the amount above was actually received at the counter right now.
                        A receipt is generated immediately. Leave unchecked to add the fee to the patient's outstanding dues and collect it later from the Billing tab.
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <button type="button"
                            onclick="document.getElementById('enrollModal').classList.add('hidden')"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2 bg-[#6a0f70] text-white text-sm font-semibold rounded-xl hover:bg-[#5a0c60] transition">
                        Confirm Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var planData = {!! $planFamilyData !!};
    function getSelectedPlan() {
        var c = document.querySelector('.enroll-plan-radio:checked');
        return c ? planData[c.value] : null;
    }
    // Is a family member selected in the dropdown? -> this is an add-on enrollment.
    function isLinked() {
        var sel = document.getElementById('familyHeadSelect');
        return !!(sel && sel.value !== '');
    }
    function updateModal() {
        var plan = getSelectedPlan();
        if (!plan) return;
        var fSec  = document.getElementById('familySection');
        var amt   = document.getElementById('enrollAmountPaid');
        var hint  = document.getElementById('enrollAmountHint');
        var aHint = document.getElementById('addonPriceHint');

        // Family box only appears for family-enabled plans.
        if (plan.family_option !== 'none') {
            fSec.classList.remove('hidden');
            if (aHint) aHint.textContent = plan.addon_price ? '(add-on price Rs. ' + plan.addon_price + ')' : '';
        } else {
            fSec.classList.add('hidden');
        }

        // Amount: use the add-on price when linking to an existing member on an
        // add-on plan; otherwise use the full plan price.
        if (plan.family_option !== 'none' && isLinked() && plan.addon_price) {
            amt.value = plan.addon_price;
            hint.textContent = 'Add-on price (linked member). Edit if needed.';
        } else {
            amt.value = plan.price;
            hint.textContent = 'Auto-filled from plan price.';
        }
    }
    document.querySelectorAll('.enroll-plan-radio').forEach(function(r) { r.addEventListener('change', updateModal); });
    document.addEventListener('change', function(e) { if (e.target.id === 'familyHeadSelect') updateModal(); });
    updateModal();
})();
</script>
@endif
