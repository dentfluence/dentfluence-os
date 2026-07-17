{{--
    resources/views/prescriptions/partials/pedo-dose-helper.blade.php

    Optional, advisory pediatric syrup dose helper.
    Converts a weight-based mg/kg/day order into millilitres of a syrup, using
    the standard formula:

        mg/day  = weight(kg) × mg/kg/day
        mg/dose = mg/day ÷ doses per day        (capped at the adult max/day)
        ml/dose = mg/dose ÷ (concentration mg per 5 ml ÷ 5)

    Nothing is saved and nothing is auto-filled — the dentist reads the
    suggestion, confirms it against the product label, and types the actual
    dose into the drug row. mg/kg/day is the clinical standard of care;
    Clark's rule is intentionally NOT used here.

    Self-contained Alpine component — no outer scope required.
--}}
<div x-data="pedoDoseHelper()" x-cloak class="mb-3 border border-gray-200 rounded-lg bg-white">
    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">
        <span>Pediatric syrup dose helper <span class="text-gray-400 font-normal">(optional)</span></span>
        <span class="text-gray-400 text-base leading-none" x-text="open ? '–' : '+'"></span>
    </button>

    <div x-show="open" class="px-4 pb-4 pt-1 border-t border-gray-100">

        {{-- Quick-pick common dental syrups — prefills the editable fields --}}
        <div class="flex flex-wrap items-center gap-1.5 mb-3">
            <span class="text-xs text-gray-400 mr-1">Quick pick:</span>
            <template x-for="d in drugs" :key="d.name">
                <button type="button" @click="pick(d)"
                        class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600 hover:border-red-300 hover:text-red-600 transition"
                        x-text="d.name"></button>
            </template>
        </div>

        {{-- Inputs (all editable) --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Weight (kg)</label>
                <input type="number" min="0" step="0.1" x-model.number="weight"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-red-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">mg/kg/day</label>
                <input type="number" min="0" step="0.5" x-model.number="mgPerKg"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-red-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Doses/day</label>
                <input type="number" min="1" max="6" step="1" x-model.number="doses"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-red-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Syrup mg/5&nbsp;ml</label>
                <input type="number" min="0" step="1" x-model.number="conc"
                       placeholder="e.g. 250"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-red-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Max mg/day <span class="text-gray-400 font-normal">(opt)</span></label>
                <input type="number" min="0" step="1" x-model.number="maxPerDay"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-red-300">
            </div>
        </div>

        {{-- Result --}}
        <div class="mt-3 rounded-lg px-3 py-2 text-sm"
             :class="ready ? 'bg-red-50 text-gray-800' : 'bg-gray-50 text-gray-400'">
            <template x-if="!ready">
                <span>Enter weight, mg/kg/day, doses and syrup strength to see the suggested millilitres.</span>
            </template>
            <template x-if="ready">
                <div>
                    <div class="font-semibold text-red-700">
                        ≈ <span x-text="round(mlPerDose)"></span> ml per dose
                        · <span x-text="doses"></span>× a day
                        <span class="text-gray-500 font-normal">(<span x-text="round(mlPerDose * doses)"></span> ml/day)</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        <span x-text="round(cappedMgPerDay)"></span> mg/day ÷ <span x-text="doses"></span>
                        = <span x-text="round(mgPerDose)"></span> mg/dose;
                        syrup <span x-text="round(mgPerMl)"></span> mg/ml
                    </div>
                    <div x-show="overMax" class="text-xs text-amber-700 mt-1 font-medium">
                        ⚠ Weight-based dose (<span x-text="round(mgPerDay)"></span> mg/day) exceeds the max you entered —
                        suggestion capped at <span x-text="round(maxPerDay)"></span> mg/day.
                    </div>
                </div>
            </template>
        </div>

        <p class="text-xs text-gray-400 mt-2">
            Advisory only. Verify against the product label and the child's clinical status before prescribing.
            Uses weight-based mg/kg dosing (not Clark's rule). Nothing here is saved — enter the confirmed dose in the drug row above.
        </p>
    </div>
</div>

@once
@push('scripts')
<script>
    function pedoDoseHelper() {
        return {
            open: false,
            weight: '', mgPerKg: '', doses: 3, conc: '', maxPerDay: '',
            // Common pediatric dental syrups — typical starting values, all editable.
            drugs: [
                { name: 'Amoxicillin',   mgPerKg: 25,   doses: 3, conc: 250, maxPerDay: 1500 },
                { name: 'Ibuprofen',     mgPerKg: 30,   doses: 3, conc: 100, maxPerDay: 1200 },
                { name: 'Paracetamol',   mgPerKg: 60,   doses: 4, conc: 250, maxPerDay: 3000 },
                { name: 'Metronidazole', mgPerKg: 22.5, doses: 3, conc: 200, maxPerDay: 1000 },
            ],
            toggle() {
                this.open = !this.open;
                if (this.open) this.syncWeight();
            },
            syncWeight() {
                const w = document.querySelector('input[name="weight"]');
                if (w && w.value && !this.weight) this.weight = parseFloat(w.value);
            },
            pick(d) {
                this.mgPerKg = d.mgPerKg;
                this.doses = d.doses;
                this.conc = d.conc;
                this.maxPerDay = d.maxPerDay;
                this.syncWeight();
            },
            get mgPerDay() {
                const v = (+this.weight) * (+this.mgPerKg);
                return isFinite(v) && v > 0 ? v : 0;
            },
            get overMax() {
                const m = +this.maxPerDay;
                return m > 0 && this.mgPerDay > m;
            },
            get cappedMgPerDay() {
                const m = +this.maxPerDay;
                return (m > 0 && this.mgPerDay > m) ? m : this.mgPerDay;
            },
            get mgPerMl() {
                const c = +this.conc;
                return c > 0 ? c / 5 : 0;
            },
            get mgPerDose() {
                const n = +this.doses;
                return n > 0 ? this.cappedMgPerDay / n : 0;
            },
            get mlPerDose() {
                return this.mgPerMl > 0 ? this.mgPerDose / this.mgPerMl : 0;
            },
            get ready() {
                return (+this.weight) > 0 && (+this.mgPerKg) > 0 && (+this.doses) > 0 && (+this.conc) > 0;
            },
            round(x) {
                return Math.round((+x) * 10) / 10;
            },
        };
    }
</script>
@endpush
@endonce
