{{--
 |  Communications Tab
 |  Shows: history of past comms + upcoming scheduled comms + manual add form
 |  Data loaded via fetch() on tab activate to keep the page fast.
 --}}
<div
    x-show="activeTab === 'communications'"
    style="display:none"
    class="w-full px-6 py-5"
    x-data="{
        comms: [],
        loading: false,
        loaded: false,

        /* form state */
        showForm: false,
        form: {
            type: 'call',
            direction: 'outgoing',
            status: 'sent',
            subject: '',
            message: '',
            scheduled_at: '',
            sent_at: '',
        },
        saving: false,

        /* filter */
        filterType: 'all',   /* all | call | whatsapp | email | sms */
        filterTime: 'all',   /* all | past | upcoming */

        /* ── load once when tab opens ── */
        init() {
            this.$watch('activeTab', val => {
                if (val === 'communications' && !this.loaded) this.fetchComms();
            });
        },

        fetchComms() {
            this.loading = true;
            fetch('{{ route('patients.communications.index', $patient) }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => { this.comms = data; this.loaded = true; })
            .catch(() => {})
            .finally(() => this.loading = false);
        },

        /* ── computed filtered list ── */
        get filteredComms() {
            const now = new Date();
            return this.comms.filter(c => {
                const typeOk = this.filterType === 'all' || c.type === this.filterType;
                let timeOk = true;
                if (this.filterTime === 'past') {
                    timeOk = c.status !== 'scheduled' || (c.scheduled_at && new Date(c.scheduled_at) <= now);
                } else if (this.filterTime === 'upcoming') {
                    timeOk = c.status === 'scheduled' && c.scheduled_at && new Date(c.scheduled_at) > now;
                }
                return typeOk && timeOk;
            });
        },

        get upcomingCount() {
            const now = new Date();
            return this.comms.filter(c => c.status === 'scheduled' && c.scheduled_at && new Date(c.scheduled_at) > now).length;
        },

        /* ── save new comm ── */
        saveComm() {
            this.saving = true;
            fetch('{{ route('patients.communications.store', $patient) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(this.form)
            })
            .then(r => r.json())
            .then(comm => {
                this.comms.unshift(comm);
                this.showForm = false;
                this.form = { type:'call', direction:'outgoing', status:'sent', subject:'', message:'', scheduled_at:'', sent_at:'' };
            })
            .catch(() => alert('Could not save. Please try again.'))
            .finally(() => this.saving = false);
        },

        /* ── delete ── */
        deleteComm(id) {
            if (!confirm('Delete this communication record?')) return;
            fetch(`{{ url('patients/' . $patient->id . '/communications') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                }
            })
            .then(() => { this.comms = this.comms.filter(c => c.id !== id); });
        },

        /* ── helpers ── */
        typeIcon(type) {
            return { call:'', whatsapp:'', email:'', sms:'' }[type] ?? '';
        },
        typeColor(type) {
            return {
                call:      'bg-blue-50 text-blue-700 border-blue-200',
                whatsapp:  'bg-green-50 text-green-700 border-green-200',
                email:     'bg-purple-50 text-purple-700 border-purple-200',
                sms:       'bg-yellow-50 text-yellow-700 border-yellow-200',
            }[type] ?? 'bg-gray-50 text-gray-700 border-gray-200';
        },
        statusColor(status) {
            return {
                sent:       'text-green-600',
                received:   'text-blue-600',
                scheduled:  'text-orange-500',
                failed:     'text-red-500',
                cancelled:  'text-gray-400',
            }[status] ?? 'text-gray-500';
        },
        formatDate(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleDateString('en-IN', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
        },
        formatDateOnly(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleDateString('en-IN', { day:'numeric', month:'short', year:'numeric' });
        },
    }"
>
    {{-- ── Header ── --}}
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="text-base font-semibold text-gray-800">Communications</h2>
            <p class="text-xs text-gray-400 mt-0.5">All calls, WhatsApp, walk-ins & emails — logged via Communication List</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- upcoming badge --}}
            <span x-show="upcomingCount > 0"
                  class="text-xs font-semibold bg-orange-100 text-orange-600 px-2.5 py-1 rounded-full">
                <span x-text="upcomingCount"></span> upcoming
            </span>
            <button @click="showForm = !showForm" dusk="comm-add"
                class="px-4 py-2 text-xs font-semibold bg-[#6a0f70] text-white hover:bg-[#380740] transition">
                + Log Communication
            </button>
        </div>
    </div>

    {{-- ── Add Communication Form ── --}}
    <div x-show="showForm" x-transition class="bg-white border border-[#6a0f70]/20 rounded-lg p-5 mb-4 space-y-4">
        <div class="text-sm font-semibold text-gray-700 mb-1">Log a Communication</div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            {{-- Type --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Channel</label>
                <select x-model="form.type" class="w-full text-sm border border-gray-200 px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="call">Call</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
            </div>
            {{-- Direction --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Direction</label>
                <select x-model="form.direction" class="w-full text-sm border border-gray-200 px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="outgoing">Outgoing</option>
                    <option value="incoming">Incoming</option>
                </select>
            </div>
            {{-- Status --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status</label>
                <select x-model="form.status" class="w-full text-sm border border-gray-200 px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="sent">Sent / Done</option>
                    <option value="received">Received</option>
                    <option value="scheduled">Scheduled (future)</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            {{-- Date --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1"
                    x-text="form.status === 'scheduled' ? 'Scheduled At' : 'Date / Time'"></label>
                <input
                    x-model="form.status === 'scheduled' ? form.scheduled_at : form.sent_at"
                    type="datetime-local"
                    class="w-full text-sm border border-gray-200 px-2 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        {{-- Subject (email only) --}}
        <div x-show="form.type === 'email'">
            <label class="block text-xs text-gray-500 mb-1">Subject</label>
            <input type="text" x-model="form.subject" placeholder="Email subject…"
                class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
        </div>

        {{-- Message / Notes --}}
        <div>
            <label class="block text-xs text-gray-500 mb-1">Message / Notes</label>
            <textarea x-model="form.message" rows="3" dusk="comm-message"
                placeholder="Conversation summary, message content, or notes…"
                class="w-full text-sm border border-gray-200 px-3 py-2 resize-none focus:outline-none focus:border-[#6a0f70]"></textarea>
        </div>

        <div class="flex justify-end gap-2">
            <button @click="showForm = false" type="button"
                class="px-4 py-2 text-xs border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button @click="saveComm()" :disabled="saving" type="button" dusk="comm-save"
                class="px-5 py-2 text-xs font-semibold bg-[#6a0f70] text-white hover:bg-[#380740] disabled:opacity-50 transition">
                <span x-text="saving ? 'Saving…' : 'Save'"></span>
            </button>
        </div>
    </div>

    {{-- ── Filters ── --}}
    <div class="flex items-center gap-2 flex-wrap mb-4">
        {{-- type filter --}}
        <div class="flex gap-1">
            @foreach(['all'=>'All','call'=>'Calls','whatsapp'=>'WhatsApp','email'=>'Email','sms'=>'SMS'] as $val => $lbl)
            <button @click="filterType='{{ $val }}'"
                :class="filterType==='{{ $val }}' ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]'"
                class="px-3 py-1 text-xs font-medium border transition-colors">
                {{ $lbl }}
            </button>
            @endforeach
        </div>
        <div class="w-px h-5 bg-gray-200 mx-1"></div>
        {{-- time filter --}}
        <div class="flex gap-1">
            @foreach(['all'=>'All Time','past'=>'History','upcoming'=>'Upcoming'] as $val => $lbl)
            <button @click="filterTime='{{ $val }}'"
                :class="filterTime==='{{ $val }}' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400'"
                class="px-3 py-1 text-xs font-medium border transition-colors">
                {{ $lbl }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Loading ── --}}
    <div x-show="loading" class="py-10 text-center text-sm text-gray-400">Loading communications…</div>

    {{-- ── Empty state ── --}}
    <div x-show="!loading && filteredComms.length === 0"
         class="bg-white border border-gray-200 rounded-lg py-14 text-center text-gray-400 text-sm">
        <p class="text-2xl mb-2"></p>
        <p>No communications found.</p>
        <p class="text-xs mt-1 text-gray-300">Log a call, WhatsApp, or email using the button above.</p>
    </div>

    {{-- ── Timeline ── --}}
    <div x-show="!loading && filteredComms.length > 0" class="space-y-2">
        <template x-for="comm in filteredComms" :key="comm.id">
            <div class="bg-white border border-gray-200 rounded-lg p-4 flex gap-4 items-start group relative">

                {{-- Icon --}}
                <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-lg border"
                    :class="typeColor(comm.type)">
                    <span x-text="typeIcon(comm.type)"></span>
                </div>

                {{-- Body --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Channel + direction --}}
                        <span class="text-xs font-semibold text-gray-700 capitalize" x-text="comm.type"></span>
                        <span class="text-[10px] text-gray-400"
                            x-text="comm.direction === 'outgoing' ? '↑ Outgoing' : '↓ Incoming'"></span>

                        {{-- Auto badge --}}
                        <span x-show="comm.is_auto"
                            class="text-[10px] px-1.5 py-0.5 bg-indigo-50 text-indigo-500 border border-indigo-200 rounded">
                            Auto
                        </span>

                        {{-- Status --}}
                        <span class="text-[10px] font-semibold uppercase tracking-wide"
                            :class="statusColor(comm.status)"
                            x-text="comm.status"></span>
                    </div>

                    {{-- Subject (email) --}}
                    <p x-show="comm.subject" class="text-xs text-gray-500 mt-0.5 font-medium" x-text="comm.subject"></p>

                    {{-- Message --}}
                    <p x-show="comm.message" class="text-sm text-gray-700 mt-1 leading-relaxed" x-text="comm.message"></p>

                    {{-- Date info --}}
                    <div class="flex gap-3 mt-1.5 flex-wrap">
                        <span x-show="comm.sent_at" class="text-[11px] text-gray-400"
                            x-text="'Sent: ' + formatDate(comm.sent_at)"></span>
                        <span x-show="comm.scheduled_at" class="text-[11px] text-orange-400 font-medium"
                            x-text="'Scheduled: ' + formatDate(comm.scheduled_at)"></span>
                        <span x-show="comm.staff_name" class="text-[11px] text-gray-300"
                            x-text="'by ' + comm.staff_name"></span>
                    </div>
                </div>

                {{-- Delete (shows on hover) --}}
                <button
                    x-show="!comm.is_auto"
                    @click="deleteComm(comm.id)"
                    class="opacity-0 group-hover:opacity-100 transition text-gray-300 hover:text-red-400 text-xs flex-shrink-0 mt-0.5"
                    title="Delete">
                    ✕
                </button>
            </div>
        </template>
    </div>

</div>{{-- /x-show communications --}}
