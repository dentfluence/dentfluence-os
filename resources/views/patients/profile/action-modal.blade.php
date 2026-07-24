
{{-- ══════════════════════════════════════════════════════════
     PATIENT ACTION MODAL (Deactivate / Delete) — with auth
══════════════════════════════════════════════════════════ --}}
<div id="patient-action-modal"
     class="hidden fixed inset-0 z-[999] flex items-center justify-center"
     style="background:rgba(0,0,0,0.45)">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 id="patient-action-title" class="text-base font-bold text-gray-800">Patient Action</h3>
            <button onclick="document.getElementById('patient-action-modal').classList.add('hidden')"
                    class="text-gray-300 hover:text-gray-500 text-xl leading-none">&times;</button>
        </div>

        <form id="patient-action-form" method="POST" action="" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_method" id="patient-action-method" value="POST">
            <input type="hidden" id="patient-action-mode" value="deactivate">

            {{-- Warning banner --}}
            <div id="patient-action-deactivate-warn"
                 class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                <strong>Deactivating</strong> this patient will mark them as inactive. They will not appear in active patient lists, but their records are preserved. You can reactivate them anytime.
            </div>
            <div id="patient-action-delete-warn"
                 class="hidden p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
                <strong>Deleting</strong> this patient is a soft delete — data is preserved in the database but the patient will be removed from all lists. This action requires your password to confirm.
            </div>

            {{-- Reason --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="Describe why this action is being taken…"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Your Password <span class="text-red-500">*</span>
                    <span class="text-xs font-normal text-gray-400 ml-1">to confirm this action</span>
                </label>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                @error('password')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        id="patient-action-submit"
                        class="flex-1 py-2 text-sm font-semibold rounded-lg text-white bg-amber-600 hover:bg-amber-700 transition-colors">
                    Deactivate
                </button>
                <button type="button"
                        onclick="document.getElementById('patient-action-modal').classList.add('hidden')"
                        class="flex-1 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modal      = document.getElementById('patient-action-modal');
    const form       = document.getElementById('patient-action-form');
    const modeInput  = document.getElementById('patient-action-mode');
    const methodInput = document.getElementById('patient-action-method');
    const submitBtn  = document.getElementById('patient-action-submit');
    const warnDeact  = document.getElementById('patient-action-deactivate-warn');
    const warnDel    = document.getElementById('patient-action-delete-warn');

    const deactivateUrl = "{{ route('patients.deactivate', $patient) }}";
    const deleteUrl     = "{{ route('patients.destroy', $patient) }}";

    // Watch mode changes and update form accordingly
    const observer = new MutationObserver(() => {
        const mode = modeInput.value;
        if (mode === 'deactivate') {
            form.action   = deactivateUrl;
            methodInput.value = 'POST';
            submitBtn.textContent = 'Deactivate Patient';
            submitBtn.className = submitBtn.className.replace(/bg-\S+/, 'bg-amber-600 hover:bg-amber-700');
            warnDeact.classList.remove('hidden');
            warnDel.classList.add('hidden');
        } else {
            form.action   = deleteUrl;
            methodInput.value = 'DELETE';
            submitBtn.textContent = 'Delete Patient';
            submitBtn.className = submitBtn.className.replace(/bg-amber-600 hover:bg-amber-700/, 'bg-red-600 hover:bg-red-700');
            warnDeact.classList.add('hidden');
            warnDel.classList.remove('hidden');
        }
    });
    if (modeInput) observer.observe(modeInput, { attributes: true, childList: true, characterData: true });
})();
</script>

