{{-- Manual Call Log Form --}}
@extends('layouts.communication')

@section('title', 'Log Communication')

@push('communication-styles')
    <link rel="stylesheet" href="{{ asset('css/communication/manager.css') }}">
@endpush

@section('communication-content')
<div class="comm-manager">

    <div class="cm-page-header">
        <div class="cm-page-header-top">
            <h1 class="cm-page-title">
                Log Communication
                <span>/ Manual Entry</span>
            </h1>
            <div class="cm-header-actions">
                <a href="{{ route('communication.manager.index') }}" class="cm-btn cm-btn-secondary">
                    ← Back to Queue
                </a>
            </div>
        </div>
    </div>

    <div class="cm-body">
        <form method="POST" action="{{ route('communication.manager.log.store') }}" id="log-form">
            @csrf

            <div class="cm-form-card">
                <h2 class="cm-form-title">New Communication Log</h2>

                {{-- Source --}}
                <div class="cm-form-group">
                    <label class="cm-form-label">Communication Source</label>
                    <div class="cm-source-selector" id="source-selector">
                        @foreach([
                            'call'      => ['emoji' => '📞', 'label' => 'Call'],
                            'whatsapp'  => ['emoji' => '💬', 'label' => 'WhatsApp'],
                            'instagram' => ['emoji' => '📸', 'label' => 'Instagram'],
                            'walkin'    => ['emoji' => '🚶', 'label' => 'Walk-in'],
                            'google'    => ['emoji' => '🔍', 'label' => 'Google'],
                            'sms'       => ['emoji' => '✉️',  'label' => 'SMS'],
                            'facebook'  => ['emoji' => '👥', 'label' => 'Facebook'],
                            'other'     => ['emoji' => '📌', 'label' => 'Other'],
                        ] as $val => $src)
                        <div class="cm-source-opt {{ old('source') === $val ? 'selected' : '' }}"
                             data-value="{{ $val }}"
                             onclick="pickSource(this)">
                            {{ $src['emoji'] }} {{ $src['label'] }}
                        </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="source" id="source-input" value="{{ old('source', 'call') }}">
                    @error('source') <p style="color:var(--cm-red);font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror
                </div>

                {{-- Person details --}}
                <div class="cm-form-row">
                    <div class="cm-form-group">
                        <label class="cm-form-label">Person Name</label>
                        <input type="text" name="person_name" class="cm-form-input"
                               placeholder="Anjali Sharma"
                               value="{{ old('person_name') }}" required>
                        @error('person_name') <p style="color:var(--cm-red);font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror
                    </div>
                    <div class="cm-form-group">
                        <label class="cm-form-label">Phone Number</label>
                        <input type="tel" name="phone" class="cm-form-input"
                               placeholder="98765 43210"
                               value="{{ old('phone') }}" required>
                    </div>
                </div>

                {{-- Classification --}}
                <div class="cm-form-group">
                    <label class="cm-form-label">Classification</label>
                    <x-communication.classification-picker
                        :selected="old('classification', 'new_patient')"
                        name="classification" />
                    @error('classification') <p style="color:var(--cm-red);font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror
                </div>

                {{-- Priority --}}
                <div class="cm-form-group">
                    <label class="cm-form-label">Priority</label>
                    <div class="cm-priority-dots" id="priority-selector">
                        @foreach(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $val => $label)
                        <div class="cm-prio {{ $val }} {{ old('priority', 'medium') === $val ? 'selected' : '' }}"
                             data-value="{{ $val }}"
                             onclick="pickPriority(this)">
                            <div class="dot"></div>
                            {{ $label }}
                        </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="priority" id="priority-input" value="{{ old('priority', 'medium') }}">
                </div>

                {{-- Note --}}
                <div class="cm-form-group">
                    <label class="cm-form-label">Note / Summary</label>
                    <textarea name="note" class="cm-form-textarea"
                              placeholder="Brief summary of the communication…">{{ old('note') }}</textarea>
                </div>

                {{-- Assign + Due Date --}}
                <div class="cm-form-row">
                    <div class="cm-form-group">
                        <label class="cm-form-label">Assign To</label>
                        <select name="assigned_to" class="cm-form-select">
                            <option value="">— Unassigned —</option>
                            @foreach($staff as $member)
                            <option value="{{ $member['id'] }}" {{ old('assigned_to') == $member['id'] ? 'selected' : '' }}>
                                {{ $member['name'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cm-form-group">
                        <label class="cm-form-label">Follow-up Due</label>
                        <input type="datetime-local" name="due_at" class="cm-form-input"
                               value="{{ old('due_at') }}">
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="cm-btn cm-btn-primary">
                        Save Communication
                    </button>
                    <a href="{{ route('communication.manager.index') }}" class="cm-btn cm-btn-secondary">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection

@push('communication-scripts')
<script>
function pickSource(el) {
    document.querySelectorAll('#source-selector .cm-source-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('source-input').value = el.dataset.value;
}

function pickPriority(el) {
    document.querySelectorAll('#priority-selector .cm-prio').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('priority-input').value = el.dataset.value;
}

// Auto-select call by default on load
document.addEventListener('DOMContentLoaded', () => {
    const defaultSource = document.querySelector('[data-value="{{ old('source', 'call') }}"]');
    if (defaultSource) defaultSource.classList.add('selected');
});
</script>
@endpush
