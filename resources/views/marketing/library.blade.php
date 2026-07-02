@extends('layouts.app')

@section('page-title', 'Marketing Library')

@section('content')
<div class="flex flex-col h-full" style="min-height: calc(100vh - 56px);">

{{-- ══ TOP BAR ══════════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-[#e8d5f0] sticky top-0 z-20">
    <div class="px-6 pt-5 pb-0 flex items-end justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Dentfluence OS</p>
            <h1 class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond] leading-tight">
                Marketing Library
            </h1>
        </div>
        <div class="flex items-center gap-2 pb-3">
            {{-- Upload button --}}
            <button onclick="openUploadModal()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#380740] text-white text-sm font-medium hover:bg-[#4e0d5a] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12V4m0 0L8 8m4-4l4 4"/>
                </svg>
                Upload Media
            </button>
        </div>
    </div>

    {{-- Tab nav --}}
    <div class="flex gap-0 px-6 mt-3 text-sm">
        <a href="{{ route('marketing.index') }}"
           class="px-4 py-2 border-b-2 border-transparent text-gray-500 hover:text-[#380740] font-medium">
            Overview
        </a>
        <a href="{{ route('marketing.library') }}"
           class="px-4 py-2 border-b-2 border-[#380740] text-[#380740] font-semibold">
            Library
        </a>
    </div>
</div>

{{-- ══ FILTER BAR ══════════════════════════════════════════════════════════ --}}
<div class="bg-[#faf5fc] border-b border-[#e8d5f0] px-6 py-3 flex flex-wrap gap-3 items-center">
    <form method="GET" action="{{ route('marketing.library') }}" class="flex flex-wrap gap-3 items-center w-full">
        <select name="treatment_type"
                class="border border-[#e8d5f0] rounded-lg px-3 py-1.5 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#c57ed6]"
                onchange="this.form.submit()">
            <option value="">All Treatments</option>
            @foreach($treatmentOptions as $val => $label)
                <option value="{{ $val }}" {{ $filterTreatment === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="photo_type"
                class="border border-[#e8d5f0] rounded-lg px-3 py-1.5 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#c57ed6]"
                onchange="this.form.submit()">
            <option value="">All Types</option>
            @foreach($photoOptions as $val => $label)
                <option value="{{ $val }}" {{ $filterPhoto === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        @if($filterTreatment || $filterPhoto)
            <a href="{{ route('marketing.library') }}"
               class="text-xs text-[#380740] underline">Clear filters</a>
        @endif

        <div class="ml-auto text-xs text-gray-400">
            {{ $approved->total() }} approved &middot; {{ $pending->total() }} pending
        </div>
    </form>
</div>

{{-- ══ MAIN CONTENT ═════════════════════════════════════════════════════════ --}}
<div class="flex-1 overflow-y-auto px-6 py-6 space-y-10">

    {{-- ── APPROVED GRID ─────────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-base font-semibold text-[#380740] mb-4 flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
            Approved for Marketing
            <span class="text-xs font-normal text-gray-400">({{ $approved->total() }})</span>
        </h2>

        @if($approved->isEmpty())
            <div class="bg-white border border-dashed border-[#e8d5f0] rounded-xl p-10 text-center text-gray-400 text-sm">
                No approved media yet. Upload photos and give consent to populate this library.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($approved as $media)
                    <div class="group relative bg-white rounded-xl overflow-hidden border border-[#e8d5f0] shadow-sm hover:shadow-md transition">
                        {{-- Thumbnail --}}
                        <div class="aspect-square bg-gray-100 relative overflow-hidden">
                            @if($media->display_url && in_array($media->media_type, ['photo']))
                                <img src="{{ $media->display_url }}"
                                     alt="{{ $media->original_filename }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-3xl">
                                    {{ $media->media_icon }}
                                </div>
                            @endif
                            {{-- Watermark badge --}}
                            @if($media->watermark_applied)
                                <span class="absolute top-1 right-1 bg-black/50 text-white text-[9px] px-1.5 py-0.5 rounded-full">
                                    WM
                                </span>
                            @endif
                        </div>
                        {{-- Meta --}}
                        <div class="p-2">
                            <p class="text-[11px] font-medium text-gray-700 truncate">
                                {{ $media->treatment_type_label ?: '—' }}
                            </p>
                            <p class="text-[10px] text-gray-400 truncate">
                                {{ $media->photo_type_label }} &middot; {{ optional($media->patient)->name }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $approved->withQueryString()->links() }}</div>
        @endif
    </section>

    {{-- ── PENDING SECTION ───────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-base font-semibold text-amber-700 mb-4 flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
            Pending — Needs Tagging or Consent
            <span class="text-xs font-normal text-gray-400">({{ $pending->total() }})</span>
        </h2>

        @if($pending->isEmpty())
            <div class="bg-white border border-dashed border-amber-200 rounded-xl p-8 text-center text-gray-400 text-sm">
                No pending items.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-[#e8d5f0] bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-[#faf5fc] text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Preview</th>
                            <th class="px-4 py-3 text-left font-medium">Patient</th>
                            <th class="px-4 py-3 text-left font-medium">Uploaded</th>
                            <th class="px-4 py-3 text-left font-medium">Missing</th>
                            <th class="px-4 py-3 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f3eaf8]">
                        @foreach($pending as $media)
                            @php
                                $missing = [];
                                if ($media->consent_status === 'pending') $missing[] = 'Consent';
                                if (!$media->photo_type) $missing[] = 'Photo Type';
                                if (!$media->tag_treatment_type) $missing[] = 'Treatment Tag';
                            @endphp
                            <tr class="hover:bg-[#fdf8ff]">
                                <td class="px-4 py-3">
                                    <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center text-xl border border-[#e8d5f0]">
                                        @if($media->display_url && $media->media_type === 'photo')
                                            <img src="{{ $media->display_url }}" class="w-full h-full object-cover">
                                        @else
                                            {{ $media->media_icon }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-700">
                                    {{ optional($media->patient)->name ?? '—' }}
                                    <p class="text-xs text-gray-400 font-normal">{{ $media->original_filename }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $media->upload_date?->format('d M Y') ?? $media->created_at->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($missing as $m)
                                            <span class="inline-block px-2 py-0.5 text-[10px] bg-amber-100 text-amber-700 rounded-full border border-amber-200">
                                                {{ $m }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        onclick="openTagModal({{ $media->id }})"
                                        class="px-3 py-1.5 text-xs rounded-lg bg-[#380740] text-white hover:bg-[#4e0d5a] transition">
                                        Tag Now
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $pending->withQueryString()->links() }}</div>
        @endif
    </section>

    {{-- ── REJECTED SECTION (collapsed by default) ───────────────────────── --}}
    @if($rejected->total() > 0)
    <section>
        <details class="group">
            <summary class="cursor-pointer text-sm font-medium text-red-600 flex items-center gap-2 list-none">
                <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                Consent Not Given
                <span class="text-xs font-normal text-gray-400">({{ $rejected->total() }})</span>
                <svg class="w-4 h-4 ml-auto group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="mt-3 overflow-x-auto rounded-xl border border-red-100 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-red-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Patient</th>
                            <th class="px-4 py-3 text-left font-medium">File</th>
                            <th class="px-4 py-3 text-left font-medium">Uploaded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-50">
                        @foreach($rejected as $media)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-700">
                                    {{ optional($media->patient)->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $media->original_filename }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $media->upload_date?->format('d M Y') ?? $media->created_at->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>
    @endif

</div>{{-- end main content --}}

</div>{{-- end page wrapper --}}

{{-- ══ UPLOAD MODAL ════════════════════════════════════════════════════════ --}}
<div id="uploadModal"
     class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4"
     onclick="if(event.target===this) closeUploadModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-[#380740]">Upload Media</h3>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                <input type="file" name="file" id="uploadFile" accept="image/*,video/mp4,application/pdf"
                       class="block w-full text-sm text-gray-500 border border-[#e8d5f0] rounded-lg px-3 py-2 cursor-pointer"
                       required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Patient ID <span class="text-gray-300">(optional)</span></label>
                    <input type="number" name="patient_id" class="w-full border border-[#e8d5f0] rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Treatment Name</label>
                    <input type="text" name="treatment_name" class="w-full border border-[#e8d5f0] rounded-lg px-3 py-2 text-sm" placeholder="e.g. Implant">
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeUploadModal()"
                        class="px-4 py-2 text-sm rounded-lg border border-[#e8d5f0] text-gray-600 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm rounded-lg bg-[#380740] text-white hover:bg-[#4e0d5a]">
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ TAG MODAL ═══════════════════════════════════════════════════════════ --}}
<div id="tagModal"
     class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4"
     onclick="if(event.target===this) closeTagModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-[#380740]">Tag This Photo</h3>
            <button onclick="closeTagModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500">All three fields are required before this photo can appear in the Marketing Library.</p>
        <form id="tagForm" class="space-y-4">
            @csrf
            <input type="hidden" id="tagMediaId" name="media_id">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient Consent</label>
                <select name="consent_status" required
                        class="w-full border border-[#e8d5f0] rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\CmsMedia::$consentOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photo Type</label>
                <select name="photo_type" required
                        class="w-full border border-[#e8d5f0] rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\CmsMedia::$photoTypeOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Treatment Type</label>
                <select name="tag_treatment_type" required
                        class="w-full border border-[#e8d5f0] rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\CmsMedia::$treatmentTypeOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeTagModal()"
                        class="px-4 py-2 text-sm rounded-lg border border-[#e8d5f0] text-gray-600 hover:bg-gray-50">
                    Later
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm rounded-lg bg-[#380740] text-white hover:bg-[#4e0d5a]">
                    Save Tags
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ JS ═══════════════════════════════════════════════════════════════════ --}}
<script>
const UPLOAD_URL = "{{ route('marketing.media.upload') }}";
const TAG_BASE   = "{{ url('marketing/media') }}";

// ── Upload Modal ──────────────────────────────────────────────────────────
function openUploadModal()  { document.getElementById('uploadModal').classList.remove('hidden'); }
function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn  = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Uploading…';

    try {
        const fd = new FormData(this);
        const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();

        if (json.success) {
            closeUploadModal();
            this.reset();
            // Immediately open the tag modal for the new record
            openTagModal(json.media_id);
        } else {
            alert('Upload failed. Please try again.');
        }
    } catch (err) {
        alert('Upload error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Upload';
    }
});

// ── Tag Modal ─────────────────────────────────────────────────────────────
function openTagModal(mediaId) {
    document.getElementById('tagMediaId').value = mediaId;
    document.getElementById('tagModal').classList.remove('hidden');
}
function closeTagModal() { document.getElementById('tagModal').classList.add('hidden'); }

document.getElementById('tagForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const mediaId = document.getElementById('tagMediaId').value;
    const fd      = new FormData(this);
    fd.append('_method', 'POST');

    try {
        const res  = await fetch(`${TAG_BASE}/${mediaId}/tag`, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success) {
            closeTagModal();
            // Reload to reflect the updated pending/approved counts
            window.location.reload();
        } else {
            alert('Could not save tags. Check all fields.');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Tags';
    }
});
</script>

@endsection
