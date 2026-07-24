{{-- Journey Timeline card (Phase 4, Slice 2) — replaces the old two-source
     "Visit Log / Timeline". One unified, permission-aware feed of everything
     that happened with this patient, served by PatientController@timeline via
     PatientJourneyService. Filters + cursor "load older" pagination. --}}
<div class="bg-white border border-gray-200 rounded-lg overflow-hidden"
     x-data="journeyTimeline()" x-init="load()">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <span class="section-title">Journey Timeline</span>
        <button x-on:click="activeTab='visits'" class="text-xs text-[#6a0f70] hover:underline font-medium">Manage Visits</button>
    </div>

    {{-- Filter pills --}}
    <div class="px-4 pt-3 flex flex-wrap gap-1.5">
        <template x-for="g in groups" :key="g.key">
            <button x-on:click="setGroup(g.key)"
                    :class="group === g.key ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'text-gray-500 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                    class="px-2.5 py-1 text-[11px] font-medium border rounded-full transition-colors"
                    x-text="g.label"></button>
        </template>
    </div>

    {{-- Events --}}
    <div class="mt-3 divide-y divide-gray-100 min-h-[80px]" x-ref="events">
        <div class="py-10 text-center text-sm text-gray-400" x-show="loading &amp;&amp; !loaded">Loading journey…</div>
    </div>

    <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-red-500" x-show="error" x-text="error"></span>
        <span class="text-xs text-gray-400" x-show="!error &amp;&amp; loaded &amp;&amp; !nextCursor">End of journey</span>
        <button x-show="nextCursor" x-on:click="loadOlder()" :disabled="loading"
                class="text-xs text-[#6a0f70] hover:underline font-medium disabled:opacity-50"
                x-text="loading ? 'Loading…' : 'Load older →'"></button>
    </div>
</div>

<script>
function journeyTimeline() {
    return {
        group: 'all',
        groups: [
            { key: 'all',       label: 'All' },
            { key: 'clinical',  label: 'Clinical' },
            { key: 'financial', label: 'Financial' },
            { key: 'comms',     label: 'Comms' },
            { key: 'consent',   label: 'Consent' },
            { key: 'reviews',   label: 'Reviews' },
        ],
        nextCursor: null,
        loading: false,
        loaded: false,
        error: '',

        async fetchPage(append) {
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams({ group: this.group });
                if (append && this.nextCursor) params.set('before', this.nextCursor);
                const r = await fetch('{{ route('patients.timeline', $patient) }}?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                if (append) this.$refs.events.insertAdjacentHTML('beforeend', d.html);
                else        this.$refs.events.innerHTML = d.html;
                this.nextCursor = d.next_cursor;
                this.loaded = true;
            } catch (e) {
                this.error = 'Could not load the journey. Try again.';
                console.error('journey timeline', e);
            } finally {
                this.loading = false;
            }
        },

        load()      { this.fetchPage(false); },
        loadOlder() { this.fetchPage(true); },
        setGroup(g) {
            if (this.group === g) return;
            this.group = g;
            this.nextCursor = null;
            this.fetchPage(false);
        },
    };
}
</script>
