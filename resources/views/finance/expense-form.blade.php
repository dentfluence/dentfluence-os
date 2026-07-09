@extends('layouts.app')
@section('page-title', ($expense ? 'Edit' : 'New') . ' Expense — Finance')

@section('content')
<div class="p-6 max-w-2xl">

    {{-- ── HEADER ── --}}
    <div class="mb-6">
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
            &nbsp;/&nbsp;
            <a href="{{ route('finance.expenses') }}" class="hover:text-[#6a0f70]">Expenses</a>
            &nbsp;/&nbsp; {{ $expense ? 'Edit' : 'New Expense' }}
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
            {{ $expense ? 'Edit Expense' : 'Record Expense' }}
        </h1>
    </div>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 mb-5">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST"
          action="{{ $expense ? route('finance.expenses.update', $expense) : route('finance.expenses.store') }}"
          class="bg-white border border-[#e8d5f0] p-6 space-y-5"
          x-data="{
              gstOn: {{ old('gst_applicable', $expense?->gst_applicable ? 'true' : 'false') }},
              recurring: {{ old('is_recurring', $expense?->is_recurring ? 'true' : 'false') }},
              payStatus: '{{ old('payment_status', $expense?->payment_status ?? 'paid') }}'
          }">
        @csrf
        @if($expense) @method('PUT') @endif

        {{-- ── SCAN BILL — fill the form from a photo (local AI, nothing leaves this PC) ── --}}
        @if(config('assistant.vision.enabled', true) && !$expense)
        <div class="bg-[#faf5fc] border border-dashed border-[#d8b8e2] rounded p-4"
             x-data="{ scanning: false, msg: '', err: false }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-[#6a0f70]">Scan a bill</p>
                    <p class="text-xs text-gray-500 mt-0.5">Snap or upload the bill — the fields below fill themselves. Just check &amp; Save.</p>
                </div>
                <button type="button"
                        @click="$refs.billFile.click()"
                        :disabled="scanning"
                        class="shrink-0 bg-[#6a0f70] text-white text-sm px-4 py-2 rounded hover:bg-[#380740] disabled:opacity-60 transition-colors">
                    <span x-show="!scanning">Scan Bill</span>
                    <span x-show="scanning">Reading…</span>
                </button>
            </div>

            {{-- capture="environment" opens the rear camera on phones; falls back to file picker on desktop --}}
            <input type="file" x-ref="billFile" accept="image/*" capture="environment" class="hidden"
                   @change="scanBill($event, $data)">

            {{-- spinner --}}
            <div x-show="scanning" class="flex items-center gap-2 text-xs text-gray-500 mt-3">
                <svg class="animate-spin h-4 w-4 text-[#6a0f70]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Reading the bill on this computer…
            </div>

            {{-- result note --}}
            <p x-show="msg" x-text="msg"
               :class="err ? 'text-red-600' : 'text-green-700'"
               class="text-xs mt-3"></p>
        </div>
        @endif

        {{-- ── PAYMENT STATUS TOGGLE ── --}}
        <div class="flex gap-0 border border-gray-300 rounded overflow-hidden mb-1">
            <button type="button"
                    @click="payStatus = 'paid'"
                    :class="payStatus === 'paid' ? 'bg-[#6a0f70] text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                    class="flex-1 text-sm py-2 px-4 font-medium transition-colors">
                ✓ Paid Now
            </button>
            <button type="button"
                    @click="payStatus = 'unpaid'"
                    :class="payStatus === 'unpaid' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                    class="flex-1 text-sm py-2 px-4 font-medium transition-colors border-l border-gray-300">
                Save as Unpaid / Bill Received
            </button>
        </div>
        <input type="hidden" name="payment_status" :value="payStatus">

        {{-- Title --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Expense Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $expense?->title) }}"
                   class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                   placeholder="e.g. Electricity Bill, Dental Supplies" required>
        </div>

        {{-- Category + Vendor --}}
        <div class="grid grid-cols-2 gap-4">
            <div x-data="{
                    addingCat: false, newCatName: '', savingCat: false, catErr: '',
                    async saveCat() {
                        if (!this.newCatName.trim()) return;
                        this.savingCat = true; this.catErr = '';
                        try {
                            const res = await fetch('{{ route('finance.expense-categories.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ name: this.newCatName.trim() })
                            });
                            const json = await res.json();
                            if (!res.ok) {
                                this.catErr = json.errors?.name?.[0] || json.message || 'Could not add category.';
                                return;
                            }
                            const opt = document.createElement('option');
                            opt.value = json.id;
                            opt.textContent = json.name;
                            this.$refs.categorySelect.appendChild(opt);
                            this.$refs.categorySelect.value = json.id;
                            this.newCatName = '';
                            this.addingCat = false;
                        } catch (e) {
                            this.catErr = 'Network error — try again.';
                        } finally {
                            this.savingCat = false;
                        }
                    }
                 }">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider">Category</label>
                    <button type="button" x-show="!addingCat"
                            @click="addingCat = true; $nextTick(() => $refs.newCatInput.focus())"
                            class="text-xs text-[#6a0f70] hover:underline">+ Add</button>
                </div>

                <select name="category_id" x-ref="categorySelect" x-show="!addingCat"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">— Select —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $expense?->category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>

                <div x-show="addingCat" class="flex gap-1">
                    <input type="text" x-ref="newCatInput" x-model="newCatName"
                           @keydown.enter.prevent="saveCat()"
                           @keydown.escape.prevent="addingCat = false; newCatName = ''; catErr = ''"
                           placeholder="New category name"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <button type="button" @click="saveCat()" :disabled="savingCat || !newCatName.trim()"
                            class="shrink-0 bg-[#6a0f70] text-white text-sm px-3 py-2 rounded disabled:opacity-50">
                        <span x-show="!savingCat">Save</span>
                        <span x-show="savingCat">…</span>
                    </button>
                    <button type="button" @click="addingCat = false; newCatName = ''; catErr = ''"
                            class="shrink-0 text-sm px-2 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                </div>
                <p x-show="catErr" x-text="catErr" class="text-xs text-red-600 mt-1"></p>
            </div>
            <div x-data="{
                    addingVendor: false, newVendorName: '', savingVendor: false, vendorErr: '',
                    async saveVendor() {
                        if (!this.newVendorName.trim()) return;
                        this.savingVendor = true; this.vendorErr = '';
                        try {
                            const res = await fetch('{{ route('finance.vendors.quick-store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ vendor_name: this.newVendorName.trim() })
                            });
                            const json = await res.json();
                            if (!res.ok) {
                                this.vendorErr = json.errors?.vendor_name?.[0] || json.message || 'Could not add vendor.';
                                return;
                            }
                            const opt = document.createElement('option');
                            opt.value = json.id;
                            opt.textContent = json.name;
                            this.$refs.vendorSelect.appendChild(opt);
                            this.$refs.vendorSelect.value = json.id;
                            this.newVendorName = '';
                            this.addingVendor = false;
                        } catch (e) {
                            this.vendorErr = 'Network error — try again.';
                        } finally {
                            this.savingVendor = false;
                        }
                    }
                 }">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider">Vendor</label>
                    <button type="button" x-show="!addingVendor"
                            @click="addingVendor = true; $nextTick(() => $refs.newVendorInput.focus())"
                            class="text-xs text-[#6a0f70] hover:underline">+ Add</button>
                </div>

                <select name="vendor_id" x-ref="vendorSelect" x-show="!addingVendor"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">— Select —</option>
                    @php
                        $staffVendors = $vendors->where('vendor_type', 'staff');
                        $otherVendors = $vendors->where('vendor_type', '!=', 'staff');
                    @endphp
                    @if($staffVendors->count())
                    <optgroup label="Staff">
                        @foreach($staffVendors as $v)
                            <option value="{{ $v->id }}" {{ old('vendor_id', $expense?->vendor_id) == $v->id ? 'selected' : '' }}>
                                {{ $v->company_name ?: $v->vendor_name }}
                            </option>
                        @endforeach
                    </optgroup>
                    @endif
                    @if($otherVendors->count())
                    <optgroup label="Vendors">
                        @foreach($otherVendors as $v)
                            <option value="{{ $v->id }}" {{ old('vendor_id', $expense?->vendor_id) == $v->id ? 'selected' : '' }}>
                                {{ $v->company_name ?: $v->vendor_name }}
                            </option>
                        @endforeach
                    </optgroup>
                    @endif
                </select>

                <div x-show="addingVendor" class="flex gap-1">
                    <input type="text" x-ref="newVendorInput" x-model="newVendorName"
                           @keydown.enter.prevent="saveVendor()"
                           @keydown.escape.prevent="addingVendor = false; newVendorName = ''; vendorErr = ''"
                           placeholder="New vendor name"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <button type="button" @click="saveVendor()" :disabled="savingVendor || !newVendorName.trim()"
                            class="shrink-0 bg-[#6a0f70] text-white text-sm px-3 py-2 rounded disabled:opacity-50">
                        <span x-show="!savingVendor">Save</span>
                        <span x-show="savingVendor">…</span>
                    </button>
                    <button type="button" @click="addingVendor = false; newVendorName = ''; vendorErr = ''"
                            class="shrink-0 text-sm px-2 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                </div>
                <p x-show="vendorErr" x-text="vendorErr" class="text-xs text-red-600 mt-1"></p>
                <p class="text-xs text-gray-400 mt-1">Quick-add sets basic details only — add GSTIN/bank info later from the Vendors tab.</p>
            </div>
        </div>

        {{-- Date + Amount --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Bill / Expense Date <span class="text-red-500">*</span></label>
                <input type="date" name="expense_date" value="{{ old('expense_date', $expense?->expense_date?->toDateString() ?? today()->toDateString()) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Amount (Rs. ) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" step="0.01" min="0"
                       value="{{ old('amount', $expense?->amount) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                       placeholder="0.00" required id="amountInput">
            </div>
        </div>

        {{-- Due Date — only shown for unpaid --}}
        <div x-show="payStatus === 'unpaid'">
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Due Date</label>
            <input type="date" name="due_date"
                   value="{{ old('due_date', $expense?->due_date?->toDateString()) }}"
                   class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                   placeholder="When is this bill due?">
            <p class="text-xs text-gray-400 mt-1">Leave blank if no fixed due date.</p>
        </div>

        {{-- GST --}}
        <div>
            <div class="flex items-center gap-3 mb-3">
                <input type="hidden" name="gst_applicable" value="0">
                <input type="checkbox" name="gst_applicable" id="gstToggle" value="1"
                       @if(old('gst_applicable', $expense?->gst_applicable)) checked @endif
                       x-model="gstOn"
                       class="h-4 w-4 text-[#6a0f70] border-gray-300">
                <label for="gstToggle" class="text-sm text-gray-700">GST Applicable</label>
            </div>
            <div x-show="gstOn" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">GST Rate (%)</label>
                    <select name="gst_rate" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        @foreach([5, 12, 18, 28] as $rate)
                            <option value="{{ $rate }}" {{ old('gst_rate', $expense?->gst_rate) == $rate ? 'selected' : '' }}>{{ $rate }}%</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">GST Amount (auto)</label>
                    <input type="text" id="gstPreview" readonly
                           class="w-full border border-gray-200 bg-gray-50 text-sm px-3 py-2 text-gray-500"
                           value="{{ $expense ? 'Rs. ' . number_format($expense->gst_amount, 2) : 'Rs. 0.00' }}">
                </div>
            </div>
        </div>

        {{-- Payment Mode + Reference — only shown for paid --}}
        <div x-show="payStatus === 'paid'" class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Payment Mode <span class="text-red-500">*</span></label>
                <select name="payment_mode" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    @foreach(['cash','upi','card','bank_transfer','cheque','other'] as $m)
                        <option value="{{ $m }}" {{ old('payment_mode', $expense?->payment_mode) === $m ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $m)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Reference / Invoice No.</label>
                <input type="text" name="payment_reference" value="{{ old('payment_reference', $expense?->payment_reference) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                       placeholder="Optional">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">
                    Paid From Account
                    @if($bankAccounts->isNotEmpty())<span class="text-red-500">*</span>@endif
                </label>
                @if($bankAccounts->isEmpty())
                <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-3 py-2">
                    No bank accounts configured yet — the voucher for this expense will be created without a linked account.
                    <a href="{{ route('finance.banking') }}" class="underline" target="_blank">Add one in Settings → Banking</a>.
                </p>
                @else
                <select name="clinic_account_id" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">-- Select Account --</option>
                    @foreach($bankAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ old('clinic_account_id', $expense?->paid_clinic_account_id) == $acc->id ? 'selected' : '' }}>
                            {{ $acc->account_name }} ({{ $acc->bank_name }})
                        </option>
                    @endforeach
                </select>
                @endif
            </div>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Description</label>
            <textarea name="description" rows="2"
                      class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                      placeholder="Optional details...">{{ old('description', $expense?->description) }}</textarea>
        </div>

        {{-- Recurring --}}
        <div>
            <div class="flex items-center gap-3 mb-2">
                <input type="hidden" name="is_recurring" value="0">
                <input type="checkbox" name="is_recurring" id="recurringToggle" value="1"
                       @if(old('is_recurring', $expense?->is_recurring)) checked @endif
                       x-model="recurring"
                       class="h-4 w-4 text-[#6a0f70] border-gray-300">
                <label for="recurringToggle" class="text-sm text-gray-700">Recurring Expense</label>
            </div>
            <div x-show="recurring" class="bg-purple-50 border border-purple-100 p-3 rounded">
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Repeat Every</label>
                <select name="recurring_period" class="border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    @foreach(['daily','weekly','monthly','quarterly','yearly'] as $p)
                        <option value="{{ $p }}" {{ old('recurring_period', $expense?->recurring_period) === $p ? 'selected' : '' }}>
                            {{ ucfirst($p) }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-purple-500 mt-1">Marking as recurring helps track expected bills (e.g. rent, electricity).</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-4 pt-2 border-t border-gray-100">
            <button type="submit"
                    dusk="expense-save"
                    class="bg-[#6a0f70] text-white text-sm px-6 py-2 hover:bg-[#380740] transition-colors">
                {{ $expense ? 'Update Expense' : 'Save Expense' }}
            </button>
            <a href="{{ route('finance.expenses') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function updateGstPreview() {
        const amt  = parseFloat(document.getElementById('amountInput').value) || 0;
        const rateEl = document.querySelector('select[name="gst_rate"]');
        const rate = rateEl ? parseFloat(rateEl.value) || 0 : 0;
        const gst  = amt * rate / 100;
        const preview = document.getElementById('gstPreview');
        if (preview) preview.value = 'Rs. ' + gst.toFixed(2);
    }
    document.getElementById('amountInput')?.addEventListener('input', updateGstPreview);
    document.querySelector('select[name="gst_rate"]')?.addEventListener('change', updateGstPreview);

    // ── Scan Bill ────────────────────────────────────────────────────────
    // Uploads the photo to the local vision model and fills the form fields.
    // Defined on window so the Alpine @change handler can call it.
    window.scanBill = async function (e, cmp) {
        // cmp = the Alpine component scope (scanning/msg/err), passed in as $data
        const file = e.target.files?.[0];
        if (!file) return;

        cmp.scanning = true;
        cmp.msg = '';
        cmp.err = false;

        try {
            const form = document.querySelector('form[action*="expenses"]');
            const token = form.querySelector('input[name="_token"]').value;

            const fd = new FormData();
            fd.append('image', file);

            const res = await fetch('{{ route('finance.expenses.scan') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: fd,
            });
            const json = await res.json();

            if (!json.ok) {
                cmp.err = true;
                cmp.msg = json.message || 'Could not read the bill. Please enter it manually.';
                return;
            }

            fillExpenseForm(json.data);
            cmp.msg = '✓ Filled from your bill — please check the fields, then Save.';
        } catch (err) {
            cmp.err = true;
            cmp.msg = 'Something went wrong reading the bill. Please enter it manually.';
        } finally {
            cmp.scanning = false;
            e.target.value = '';           // allow re-scanning the same file
        }
    };

    // Set a field's value only when the AI actually read something (never blank
    // out what's already there).
    function setVal(selector, value) {
        if (value === null || value === undefined || value === '') return;
        const el = document.querySelector(selector);
        if (!el) return;
        el.value = value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fillExpenseForm(d) {
        const form = document.querySelector('form[action*="expenses"]');

        setVal('input[name="title"]', d.title);
        setVal('#amountInput', d.amount);
        setVal('input[name="expense_date"]', d.expense_date);
        setVal('input[name="payment_reference"]', d.reference);
        setVal('textarea[name="description"]', d.description);

        // Dropdowns — only preselect if the matched id exists in the options.
        if (d.category_id) setVal('select[name="category_id"]', d.category_id);
        if (d.vendor_id)   setVal('select[name="vendor_id"]', d.vendor_id);
        if (d.payment_mode) setVal('select[name="payment_mode"]', d.payment_mode);

        // GST: flip the checkbox so Alpine's x-model reveals the rate row.
        if (d.gst_applicable) {
            const gst = document.getElementById('gstToggle');
            if (gst && !gst.checked) {
                gst.checked = true;
                gst.dispatchEvent(new Event('input', { bubbles: true }));  // Alpine x-model
                gst.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (d.gst_rate) setVal('select[name="gst_rate"]', d.gst_rate);
        }

        updateGstPreview();
    }
</script>
@endpush
@endsection
