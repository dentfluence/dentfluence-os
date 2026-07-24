{{-- ═══════════════════════════════════════════════════════════
     BILLING TAB
════════════════════════════��═══════ --}}
<div x-show="activeTab === 'billing'" style="display:none" class="w-full px-6 py-5">
<div>

    @php
        // ── Billing summary from new Invoice model ────────────────────────────
        $totalBilled      = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->total_amount);
        $totalCollected   = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->paid_amount);
        $totalOutstanding = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->balance_due);
        $pendingPrompts   = ($billingPrompts ?? collect())->where('status', 'pending');
        $walletBalance    = (float) ($wallet->balance_total ?? 0);
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_280px] gap-5">

        {{-- ══ MAIN COLUMN ══ --}}
        <div class="space-y-4">

            {{-- Summary cards --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Billed</div>
                    <div class="text-xl font-bold text-gray-800">Rs.  {{ number_format($totalBilled, 0) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ ($invoices ?? collect())->count() }} invoice{{ ($invoices ?? collect())->count() !== 1 ? 's' : '' }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Collected</div>
                    <div class="text-xl font-bold text-green-700">Rs.  {{ number_format($totalCollected, 0) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">
                        {{ $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100) : 0 }}% collected
                    </div>
                </div>
                <div class="{{ $totalOutstanding > 0 ? 'cursor-pointer hover:border-red-400 hover:shadow-md' : '' }} bg-white border border-gray-200 rounded-lg p-4 transition"
                     @if($totalOutstanding > 0) onclick="openQuickPayModal()" title="Click to record payment" @endif>
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Outstanding</div>
                    <div class="text-xl font-bold {{ $totalOutstanding > 0 ? 'text-red-600' : 'text-gray-800' }}">
                        Rs.  {{ number_format($totalOutstanding, 0) }}
                    </div>
                    @if($totalOutstanding > 0)
                        <div class="text-[10px] text-red-400 mt-0.5 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Tap to pay
                        </div>
                    @else
                        <div class="text-[10px] text-green-600 mt-0.5">All clear</div>
                    @endif
                </div>
            </div>

            {{-- ── Billing Prompts ──────────────────────────────────────── --}}
            @if($pendingPrompts->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-amber-200 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span class="text-xs font-semibold text-amber-800">{{ $pendingPrompts->count() }} Pending Billing Prompt{{ $pendingPrompts->count() > 1 ? 's' : '' }}</span>
                </div>
                <div class="divide-y divide-amber-100">
                    @foreach($pendingPrompts as $prompt)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800">{{ $prompt->description }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ $prompt->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            {{-- Opens the editable draft invoice, pre-filled with the visit's treatments --}}
                            <a href="{{ route('billing.createFromPrompt', [$patient, $prompt]) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition">
                                Build Invoice
                            </a>
                            <form method="POST" action="{{ route('billing.dismissPrompt', $prompt) }}" class="inline">
                                @csrf
                                <button type="submit" onclick="return confirm('Dismiss this billing prompt without invoicing?')"
                                        class="px-3 py-1.5 text-[11px] font-medium border border-amber-300 text-amber-700 rounded hover:bg-amber-100 transition">
                                    Dismiss
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @php
                // Invoice-tied receipts (issued when a payment is recorded against an invoice)
                $invoiceReceipts = ($invoices ?? collect())->flatMap(fn($i) => $i->receipts ?? collect());
                foreach ($invoiceReceipts as $rcpt) {
                    $rcpt->view_url = route('billing.receipt', [$rcpt->invoice, $rcpt]);
                }

                // Advance payments straight into the wallet (no invoice yet) — these are real
                // money received too, so they belong in the same Receipts list. Document is
                // printed from the Wallet module (source='advance' → labelled "Receipt" there).
                $advanceReceipts = (isset($wallet) && $wallet)
                    ? $wallet->transactions()
                        ->where('direction', 'credit')
                        ->where('source', 'advance')
                        ->get()
                        ->map(function ($tx) use ($patient) {
                            $tx->receipt_number = 'ADV-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT);
                            $tx->receipt_date   = $tx->created_at;
                            $tx->reference_no   = null;
                            $tx->invoice        = null;
                            $tx->view_url       = route('finance.wallets.credit-note', [$patient, $tx]);
                            return $tx;
                        })
                    : collect();

                $allReceipts   = $invoiceReceipts->concat($advanceReceipts)->sortByDesc('receipt_date');
                $allFinalBills = ($invoices ?? collect())->filter(fn($i) => $i->finalBill)->map(fn($i) => $i->finalBill)->sortByDesc('generated_date');
            @endphp

            {{-- ── Invoices + Receipts side by side ──────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Invoices --}}
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="section-title">Invoices</span>
                        <a href="{{ route('billing.create', ['patient_id' => $patient->id]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            New Invoice
                        </a>
                    </div>
                    @if(($invoices ?? collect())->isEmpty())
                        <div class="py-10 text-center flex-1">
                            <p class="text-sm font-semibold text-gray-500">No invoices yet</p>
                            <p class="text-xs text-gray-400 mt-1">Create one via "New Invoice".</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 flex-1">
                            @foreach($invoices as $inv)
                            @php
                                $badgeCls = match($inv->status) {
                                    'paid'      => 'bg-green-50 text-green-700 border-green-200',
                                    'partial'   => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
                                    default     => 'bg-blue-50 text-blue-700 border-blue-200',
                                };
                                $badgeLabel = match($inv->status) {
                                    'paid'      => 'Paid',
                                    'partial'   => 'Partial',
                                    'cancelled' => 'Cancelled',
                                    default     => 'Unpaid',
                                };
                                $invCanDelete = $inv->status !== 'paid';
                            @endphp
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/60 transition group">
                                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
                                        <line x1="16" y1="8" x2="8" y2="8"/><line x1="16" y1="12" x2="8" y2="12"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-bold text-gray-800">{{ $inv->invoice_number ?? '—' }}</span>
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $badgeCls }}">{{ $badgeLabel }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ $inv->invoice_date?->format('d M Y') }}
                                        @if($inv->items->count()) · {{ $inv->items->count() }} item{{ $inv->items->count() > 1 ? 's' : '' }} @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-xs font-bold text-gray-800">Rs. {{ number_format($inv->total_amount, 0) }}</div>
                                    @if($inv->balance_due > 0)
                                        <div class="text-[10px] text-red-500">Due Rs. {{ number_format($inv->balance_due, 0) }}</div>
                                    @elseif($inv->paid_amount > 0)
                                        <div class="text-[10px] text-green-600">Paid Rs. {{ number_format($inv->paid_amount, 0) }}</div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                    <a href="{{ route('billing.show', $inv) }}"
                                            class="w-7 h-7 flex items-center justify-center rounded hover:bg-blue-50 text-gray-400 hover:text-blue-600" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('billing.print', $inv) }}" target="_blank"
                                       class="w-7 h-7 flex items-center justify-center rounded hover:bg-amber-50 text-gray-400 hover:text-[#b45309]" title="Print">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    </a>
                                    @if($invCanDelete)
                                    <button onclick="openDeleteModal({{ $inv->id }}, '{{ $inv->invoice_number ?? 'Invoice #'.$inv->id }}')"
                                            class="w-7 h-7 flex items-center justify-center rounded hover:bg-red-50 text-gray-400 hover:text-red-600" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Receipts --}}
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="section-title">Receipts</span>
                        <span class="text-xs text-gray-400">{{ $allReceipts->count() }} receipt{{ $allReceipts->count() !== 1 ? 's' : '' }}</span>
                    </div>
                    @if($allReceipts->isEmpty())
                        <div class="py-10 text-center flex-1">
                            <p class="text-sm font-semibold text-gray-500">No receipts yet</p>
                            <p class="text-xs text-gray-400 mt-1">Generated when a payment is recorded.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 flex-1">
                            @foreach($allReceipts as $rcpt)
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/60 group">
                                <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 14l2 2 4-4"/><rect x="3" y="5" width="18" height="14" rx="2"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-bold text-gray-800 font-mono">{{ $rcpt->receipt_number }}</div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ $rcpt->receipt_date?->format('d M Y') }}
                                        · {{ ucfirst(str_replace('_',' ',$rcpt->payment_mode ?? '')) }}
                                        @if($rcpt->reference_no) · {{ $rcpt->reference_no }} @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-xs font-bold text-green-700">Rs. {{ number_format($rcpt->amount, 0) }}</div>
                                    @if($rcpt->invoice)
                                        <div class="text-[10px] text-gray-400 font-mono">{{ $rcpt->invoice->invoice_number }}</div>
                                    @else
                                        <div class="text-[10px] text-purple-500 font-mono">Advance · Wallet</div>
                                    @endif
                                </div>
                                <div class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                    <a href="{{ $rcpt->view_url }}" target="_blank"
                                       class="w-7 h-7 flex items-center justify-center rounded hover:bg-green-50 text-gray-400 hover:text-green-600" title="View Receipt">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- /invoices+receipts grid --}}

            {{-- ── Final Bills ───────────────────────────────────────────── --}}
            @if($allFinalBills->count())
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Final Bills</span>
                    <span class="text-xs text-gray-400">{{ $allFinalBills->count() }} bill{{ $allFinalBills->count() > 1 ? 's' : '' }}</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($allFinalBills as $fb)
                    <div class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50/60 group">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-800 font-mono">{{ $fb->bill_number }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $fb->generated_date?->format('d M Y') }}
                                @if($fb->invoice) · Inv {{ $fb->invoice->invoice_number ?? '#'.$fb->invoice_id }} @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-bold text-purple-700">Rs.  {{ number_format($fb->total_amount ?? 0, 0) }}</div>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                            @if($fb->invoice)
                            <a href="{{ route('billing.finalBill', $fb->invoice) }}" target="_blank"
                               class="w-8 h-8 flex items-center justify-center rounded hover:bg-purple-50 text-gray-400 hover:text-purple-600" title="View Final Bill">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Patient Ledger ────────────────────────────────────────── --}}
            @php
                // Build ledger entries: invoices (debit) + receipts (credit), sorted by date
                $ledgerEntries = collect();

                foreach (($invoices ?? collect()) as $inv) {
                    $ledgerEntries->push([
                        'date'        => $inv->invoice_date,
                        'sort_key'    => $inv->invoice_date?->format('Y-m-d') . '_A_' . $inv->id,
                        'type'        => 'invoice',
                        'ref'         => $inv->invoice_number ?? 'INV-'.$inv->id,
                        'description' => $inv->items->count() . ' item' . ($inv->items->count() !== 1 ? 's' : ''),
                        'debit'       => (float) $inv->total_amount,
                        'credit'      => 0,
                        'status'      => $inv->status,
                        'inv'         => $inv,
                        'rcpt'        => null,
                    ]);

                    foreach ($inv->receipts ?? [] as $rcpt) {
                        $ledgerEntries->push([
                            'date'        => $rcpt->receipt_date,
                            'sort_key'    => $rcpt->receipt_date?->format('Y-m-d') . '_B_' . $rcpt->id,
                            'type'        => 'receipt',
                            'ref'         => $rcpt->receipt_number,
                            'description' => ucfirst(str_replace('_', ' ', $rcpt->payment_mode ?? '')) . ($rcpt->reference_no ? ' · '.$rcpt->reference_no : ''),
                            'debit'       => 0,
                            'credit'      => (float) $rcpt->amount,
                            'status'      => 'paid',
                            'inv'         => $inv,
                            'rcpt'        => $rcpt,
                        ]);
                    }
                }

                $ledgerEntries = $ledgerEntries->sortBy('sort_key')->values();

                // Compute running balance (debit increases balance owed, credit decreases)
                $runningBalance = 0;
                $ledgerRows = [];
                foreach ($ledgerEntries as $entry) {
                    $runningBalance += $entry['debit'] - $entry['credit'];
                    $entry['balance'] = $runningBalance;
                    $ledgerRows[] = $entry;
                }
            @endphp

            @if(count($ledgerRows))
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <span class="section-title">Ledger</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1 text-gray-400"><span class="w-2 h-2 rounded-full bg-red-300 inline-block"></span>Debit</span>
                        <span class="flex items-center gap-1 text-gray-400"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>Credit</span>
                        <span class="font-semibold {{ $runningBalance > 0 ? 'text-red-600' : 'text-green-700' }}">
                            Balance: Rs. {{ number_format(abs($runningBalance), 0) }} {{ $runningBalance > 0 ? 'Due' : ($runningBalance < 0 ? 'Advance' : 'Clear') }}
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="text-left px-4 py-2.5 font-medium text-gray-500 w-24">Date</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500">Type</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500">Ref #</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500 hidden sm:table-cell">Description</th>
                                <th class="text-right px-3 py-2.5 font-medium text-red-400">Debit</th>
                                <th class="text-right px-3 py-2.5 font-medium text-green-600">Credit</th>
                                <th class="text-right px-3 py-2.5 font-medium text-gray-500">Balance</th>
                                <th class="text-center px-3 py-2.5 font-medium text-gray-500">Status</th>
                                <th class="px-3 py-2.5 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($ledgerRows as $row)
                            @php
                                $isInvoice = $row['type'] === 'invoice';
                                $rowStatus = $row['status'];
                                $statusBadge = match($rowStatus) {
                                    'paid'      => ['bg-green-50 text-green-700 border-green-200',  'Paid'],
                                    'partial'   => ['bg-amber-50 text-amber-700 border-amber-200',  'Partial'],
                                    'cancelled' => ['bg-gray-100 text-gray-500 border-gray-200',    'Cancelled'],
                                    default     => ['bg-red-50 text-red-600 border-red-200',         'Unpaid'],
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/60 {{ $isInvoice ? '' : 'bg-green-50/20' }}">
                                <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">
                                    {{ $row['date']?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @if($isInvoice)
                                        <span class="inline-flex items-center gap-1 font-semibold text-amber-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/></svg>
                                            Invoice
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 font-semibold text-green-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l2 2 4-4"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
                                            Receipt
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 font-mono font-semibold text-gray-700">
                                    @if($isInvoice)
                                        <a href="{{ route('billing.show', $row['inv']) }}"
                                                class="hover:text-[#6a0f70] hover:underline">{{ $row['ref'] }}</a>
                                    @else
                                        @if($row['rcpt'] && $row['inv'])
                                            <a href="{{ route('billing.receipt', [$row['inv'], $row['rcpt']]) }}" target="_blank"
                                               class="hover:text-green-700 hover:underline">{{ $row['ref'] }}</a>
                                        @else
                                            {{ $row['ref'] }}
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-400 hidden sm:table-cell max-w-[140px] truncate">
                                    {{ $row['description'] }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold {{ $row['debit'] > 0 ? 'text-red-500' : 'text-gray-200' }}">
                                    {{ $row['debit'] > 0 ? 'Rs. '.number_format($row['debit'], 0) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold {{ $row['credit'] > 0 ? 'text-green-600' : 'text-gray-200' }}">
                                    {{ $row['credit'] > 0 ? 'Rs. '.number_format($row['credit'], 0) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold {{ $row['balance'] > 0 ? 'text-red-600' : ($row['balance'] < 0 ? 'text-blue-600' : 'text-green-600') }}">
                                    Rs. {{ number_format(abs($row['balance']), 0) }}
                                    @if($row['balance'] > 0)<span class="text-[9px] font-normal ml-0.5">DR</span>@elseif($row['balance'] < 0)<span class="text-[9px] font-normal ml-0.5">CR</span>@endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if($isInvoice)
                                        <a href="{{ route('billing.print', $row['inv']) }}" target="_blank"
                                           class="text-gray-300 hover:text-[#b45309]" title="Print Invoice">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        </a>
                                    @elseif($row['rcpt'] && $row['inv'])
                                        <a href="{{ route('billing.receipt', [$row['inv'], $row['rcpt']]) }}" target="_blank"
                                           class="text-gray-300 hover:text-green-600" title="View Receipt">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 border-t-2 border-gray-200">
                                <td colspan="4" class="px-4 py-2.5 text-xs font-semibold text-gray-600">Totals</td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold text-red-600">
                                    Rs. {{ number_format($ledgerEntries->sum('debit'), 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold text-green-600">
                                    Rs. {{ number_format($ledgerEntries->sum('credit'), 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold {{ $runningBalance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    Rs. {{ number_format(abs($runningBalance), 0) }}
                                    <span class="font-normal text-[9px]">{{ $runningBalance > 0 ? 'DUE' : ($runningBalance < 0 ? 'ADV' : 'NIL') }}</span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

        </div>
        {{-- /main column --}}

        {{-- ══ SIDEBAR ══ --}}
        <div class="space-y-3">

            {{-- Payment Summary --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Summary</div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Invoiced</span>
                        <span class="font-semibold">Rs.  {{ number_format($totalBilled, 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Collected</span>
                        <span class="font-semibold text-green-700">Rs.  {{ number_format($totalCollected, 0) }}</span>
                    </div>
                    <div class="border-t pt-2 flex justify-between">
                        <span class="text-gray-500">Outstanding</span>
                        <span class="font-bold {{ $totalOutstanding > 0 ? 'text-red-600' : 'text-gray-800' }}">
                            Rs.  {{ number_format($totalOutstanding, 0) }}
                        </span>
                    </div>
                </div>
                @if($totalBilled > 0)
                @php $collectPct = min(100, round(($totalCollected / $totalBilled) * 100)); @endphp
                <div class="mt-3">
                    <div class="flex justify-between text-[10px] text-gray-400 mb-1">
                        <span>Collection rate</span><span>{{ $collectPct }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $collectPct === 100 ? 'bg-green-500' : 'bg-[#6a0f70]' }}" style="width: {{ $collectPct }}%"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Wallet Balance --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-2">Wallet Balance</div>
                <div class="text-2xl font-bold {{ $walletBalance > 0 ? 'text-[#6a0f70]' : 'text-gray-400' }} mb-1">
                    Rs.  {{ number_format($walletBalance, 0) }}
                </div>
                @if($walletBalance > 0)
                <div class="space-y-1 text-xs text-gray-500">
                    @if(($wallet->balance_promotional ?? 0) > 0)
                        <div class="flex justify-between">
                            <span>Promotional</span>
                            <span class="font-medium text-amber-700">Rs.  {{ number_format($wallet->balance_promotional, 0) }}</span>
                        </div>
                    @endif
                    @if(($wallet->balance_permanent ?? 0) > 0)
                        <div class="flex justify-between">
                            <span>Permanent</span>
                            <span class="font-medium text-green-700">Rs.  {{ number_format($wallet->balance_permanent, 0) }}</span>
                        </div>
                    @endif
                </div>
                @else
                    <p class="text-xs text-gray-400">No credits in wallet.</p>
                @endif
            </div>

            {{-- Membership Status (compact) --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-purple-300 transition"
                 onclick="activeTab='membership'" x-on:click="activeTab='membership'">
                <div class="section-title mb-2">AOCP Membership</div>
                @if($activeMembership ?? null)
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0
                            {{ $activeMembership->days_remaining <= 30 ? 'bg-amber-400' : 'bg-green-500' }}"></span>
                        <span class="text-sm font-bold text-gray-800">{{ $activeMembership->plan->plan_name }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">
                        Expires {{ $activeMembership->end_date->format('d M Y') }}
                    </p>
                    <p class="text-xs {{ $activeMembership->days_remaining <= 30 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                        {{ $activeMembership->days_remaining }} days left
                    </p>
                @else
                    <p class="text-sm font-semibold text-gray-400">Not enrolled</p>
                    <p class="text-xs text-gray-400 mt-0.5">Tap to enroll →</p>
                @endif
            </div>

            {{-- Invoice Status Breakdown --}}
            @if(($invoices ?? collect())->isNotEmpty())
            @php
                $paidCount      = ($invoices ?? collect())->where('status','paid')->count();
                $partialCount   = ($invoices ?? collect())->where('status','partial')->count();
                $draftCount     = ($invoices ?? collect())->whereIn('status',['draft','pending'])->count();
            @endphp
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Invoice Status</div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-gray-600">Paid</span></span>
                        <span class="font-semibold text-green-700">{{ $paidCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span><span class="text-gray-600">Partial</span></span>
                        <span class="font-semibold text-amber-600">{{ $partialCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400"></span><span class="text-gray-600">Draft</span></span>
                        <span class="font-semibold text-blue-600">{{ $draftCount }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Quick actions --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Quick Actions</div>
                <div class="space-y-2">
                    <a href="{{ route('billing.create', ['patient_id' => $patient->id]) }}"
                       class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-medium text-gray-700 border border-gray-200 rounded hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5ff] transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        New Invoice
                    </a>
                    <a href="{{ route('billing.index') }}"
                       class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-medium text-gray-700 border border-gray-200 rounded hover:border-gray-400 hover:text-gray-800 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        All Invoices
                    </a>
                </div>
            </div>

        </div>
        {{-- /sidebar --}}

    </div>
</div>{{-- /billing wrapper --}}

{{-- ── Invoice Drawer (slide-over) ────────────────────────────────────────── --}}
<div id="invoiceDrawer"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)closeInvoiceDrawer()">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    {{-- Panel --}}
    <div id="invoiceDrawerPanel"
         class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl flex flex-col
                translate-x-full transition-transform duration-300 ease-out">
        {{-- Drawer header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
            <div>
                <h3 class="font-semibold text-gray-800" id="drawerInvoiceTitle">Invoice</h3>
                <p class="text-xs text-gray-400 mt-0.5">Patient: {{ $patient->name }}</p>
            </div>
            <button onclick="closeInvoiceDrawer()"
                    class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 text-xl leading-none">
                &times;
            </button>
        </div>
        {{-- Drawer content (populated via fetch) --}}
        <div id="invoiceDrawerContent" class="flex-1 overflow-y-auto px-5 py-4">
            <div id="invoiceDrawerLoader" class="flex items-center justify-center py-16">
                <div class="text-center text-gray-400">
                    <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <p class="text-sm">Loading invoice…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openInvoiceDrawer(invoiceId, invoiceRef) {
    const drawer  = document.getElementById('invoiceDrawer');
    const panel   = document.getElementById('invoiceDrawerPanel');
    const content = document.getElementById('invoiceDrawerContent');
    const loader  = document.getElementById('invoiceDrawerLoader');
    const title   = document.getElementById('drawerInvoiceTitle');

    title.textContent = invoiceRef || 'Invoice';
    content.innerHTML = loader.outerHTML; // reset to spinner
    drawer.classList.remove('hidden');
    requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

    fetch('/billing/' + invoiceId + '/panel', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(html => { content.innerHTML = html; })
    .catch(() => { content.innerHTML = '<p class="text-red-500 text-sm p-4">Failed to load invoice. Please try again.</p>'; });
}

function closeInvoiceDrawer() {
    const panel = document.getElementById('invoiceDrawerPanel');
    const drawer = document.getElementById('invoiceDrawer');
    panel.classList.add('translate-x-full');
    setTimeout(() => drawer.classList.add('hidden'), 300);
}
</script>

{{-- ── Invoice Delete Auth Modal (shared for all invoice rows) ────────────── --}}
<div id="patientDeleteModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">Delete Invoice</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Deleting <strong id="deleteModalRef"></strong> — provide a reason and your password.
                </p>
            </div>
            <button onclick="document.getElementById('patientDeleteModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-xs text-red-700">
            Deleted invoices are permanently removed. The action is logged with your name and reason.
        </div>
        <form id="deleteModalForm" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason for deletion <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="e.g. Duplicate invoice, created in error..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
                <p class="text-xs text-gray-400 mt-1">Stored permanently in the audit log.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Your password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required
                       placeholder="Enter your login password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-red-600 text-white font-medium text-sm rounded-lg hover:bg-red-700">
                    Confirm Delete
                </button>
                <button type="button"
                        onclick="document.getElementById('patientDeleteModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function openDeleteModal(invoiceId, invoiceRef) {
    document.getElementById('deleteModalRef').textContent = invoiceRef;
    document.getElementById('deleteModalForm').action = '/billing/' + invoiceId + '/delete-auth';
    document.getElementById('patientDeleteModal').classList.remove('hidden');
}
document.getElementById('patientDeleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

{{-- Close the billing tab div HERE — before the quick pay modal, so the modal is always in the DOM --}}
</div>{{-- /x-show billing --}}
