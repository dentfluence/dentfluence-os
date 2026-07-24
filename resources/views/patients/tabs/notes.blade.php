{{-- ════════════════════════════════════
     NOTES & LOGS TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'notes'" style="display:none" class="w-full px-6 py-5">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-5">
            <div class="space-y-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
                    <div class="flex gap-2 flex-wrap">
                        @foreach(['internal'=>'Internal Note','staff'=>'Staff Note','call'=>'Call Log','whatsapp'=>'WhatsApp Log'] as $type => $label)
                        <button type="button" x-on:click="noteType='{{ $type }}'"
                            :class="noteType==='{{ $type }}' ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200'"
                            class="px-3 py-1 text-xs font-medium border transition-colors">{{ $label }}</button>
                        @endforeach
                    </div>
                    <textarea x-model="newNote" rows="3" placeholder="Add a note, log a call, or record a message…"
                        dusk="note-input"
                        class="w-full text-sm border border-gray-200 px-3 py-2 resize-none focus:outline-none focus:border-[#6a0f70]"></textarea>
                    <div class="flex items-center justify-between">
                        <span x-show="noteSaveError" x-text="noteSaveError" class="text-xs text-red-500"></span>
                        <button type="button" @click="saveNote()" :disabled="noteSaving" dusk="note-save" class="ml-auto px-4 py-2 text-xs bg-[#6a0f70] text-white hover:bg-[#380740] transition font-semibold disabled:opacity-50" x-text="noteSaving ? 'Saving…' : 'Save Note'"></button>
                    </div>
                </div>
                <template x-if="relationshipNotes.length === 0">
                    <div class="bg-white border border-gray-200 rounded-lg py-14 text-center text-gray-400 text-sm">No notes or logs yet.</div>
                </template>
                <template x-for="note in relationshipNotes" :key="'nl-'+note.id">
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs text-gray-400 font-medium">Internal Note</span>
                            <span class="text-[10px] text-gray-300" x-text="note.created_at ? new Date(note.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}) : ''"></span>
                        </div>
                        <p class="text-sm text-gray-700 mt-2 leading-relaxed" x-text="note.note"></p>
                    </div>
                </template>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Audit Trail</div>
                <div class="space-y-2 text-xs text-gray-500">
                    <div class="flex items-start gap-2 py-1.5 border-b border-gray-50">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 flex-shrink-0 mt-1.5"></span>
                        <div><span class="font-medium text-gray-700">Patient registered</span><span class="block text-gray-400">{{ $patient->created_at->format('d M Y, h:i A') }}</span></div>
                    </div>
                    @if($patient->last_visit_date)
                    <div class="flex items-start gap-2 py-1.5 border-b border-gray-50">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 flex-shrink-0 mt-1.5"></span>
                        <div><span class="font-medium text-gray-700">Last visit</span><span class="block text-gray-400">{{ \Carbon\Carbon::parse($patient->last_visit_date)->format('d M Y') }}</span></div>
                    </div>
                    @endif
                </div>
            </div>
        </div>{{-- /grid notes --}}
    </div>
{{-- /x-show notes --}}
