@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('patients.index') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] flex items-center gap-1 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Patients
        </a>
        <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Import Patients</h1>
        <p class="text-sm text-gray-500 mt-1">Upload an Excel or CSV file from Clinicia, Bestosys, or any app.</p>
    </div>

    {{-- Errors --}}
    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Upload form --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <form action="{{ route('settings.data.import.preview') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Source selector --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Where is this data from?</label>
                <div class="grid grid-cols-3 gap-3">
                    @foreach(['clinicia' => 'Clinicia', 'bestosys' => 'Bestosys', 'generic' => 'Other / Generic'] as $value => $label)
                        <label class="source-option relative flex flex-col items-center gap-2 border-2 rounded-lg p-3 cursor-pointer transition-all
                            {{ old('source', 'generic') === $value ? 'border-[#6a0f70] bg-[#fdf5ff]' : 'border-gray-200 hover:border-[#6a0f70]' }}">
                            <input type="radio" name="source" value="{{ $value }}" class="sr-only"
                                {{ old('source', 'generic') === $value ? 'checked' : '' }}>
                            <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                            @if($value !== 'generic')
                                <span class="text-[10px] text-[#6a0f70] font-semibold uppercase tracking-wide">Auto-mapped</span>
                            @else
                                <span class="text-[10px] text-gray-400">Common formats</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- File upload --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Upload File</label>
                <div id="drop-zone"
                    class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-[#6a0f70] transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 text-gray-400" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <p class="text-sm text-gray-600">Click to choose or drag & drop your file here</p>
                    <p class="text-xs text-gray-400 mt-1">Supports .xlsx, .xls, .csv — max 5 MB</p>
                    <p id="file-name" class="text-sm text-[#6a0f70] font-medium mt-2 hidden"></p>
                    <input id="file-input" type="file" name="file" accept=".xlsx,.xls,.csv" class="hidden">
                </div>
            </div>

            {{-- Template download --}}
            <div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                <strong>Tip:</strong> If you have a different format, download our blank template and paste your data into it.
                <div class="mt-2 flex gap-3">
                    <a href="{{ route('settings.data.import.template','clinicia') }}" class="text-[#6a0f70] underline text-xs">Clinicia template</a>
                    <a href="{{ route('settings.data.import.template','bestosys') }}" class="text-[#6a0f70] underline text-xs">Bestosys template</a>
                    <a href="{{ route('settings.data.import.template','generic') }}" class="text-[#6a0f70] underline text-xs">Generic template</a>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-[#6a0f70] text-white py-2.5 text-sm font-medium hover:bg-[#380740] transition-colors rounded">
                Preview Import →
            </button>
        </form>
    </div>
</div>

<script>
// Source card toggle
document.querySelectorAll('.source-option').forEach(label => {
    label.addEventListener('click', () => {
        document.querySelectorAll('.source-option').forEach(l => {
            l.classList.remove('border-[#6a0f70]', 'bg-[#fdf5ff]');
            l.classList.add('border-gray-200');
        });
        label.classList.add('border-[#6a0f70]', 'bg-[#fdf5ff]');
        label.classList.remove('border-gray-200');
    });
});

// File drop zone
const zone  = document.getElementById('drop-zone');
const input = document.getElementById('file-input');
const label = document.getElementById('file-name');

zone.addEventListener('click', () => input.click());

input.addEventListener('change', () => {
    if (input.files[0]) {
        label.textContent = '' + input.files[0].name;
        label.classList.remove('hidden');
    }
});

['dragover','dragenter'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('border-[#6a0f70]','bg-[#fdf5ff]'); }));
['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('border-[#6a0f70]','bg-[#fdf5ff]'); }));
zone.addEventListener('drop', ev => {
    const file = ev.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        label.textContent = '' + file.name;
        label.classList.remove('hidden');
    }
});
</script>
@endsection
