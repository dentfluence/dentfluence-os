@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('inventory.products') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] flex items-center gap-1 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Products
        </a>
        <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Import Products</h1>
        <p class="text-sm text-gray-500 mt-1">Bulk-add Clinical products — and their vendors — from an Excel file. The quickest way to onboard an existing catalogue.</p>
    </div>

    {{-- Errors --}}
    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Import result from a previous run --}}
    @if(session('import_success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded">
            {{ session('import_success') }}
        </div>
    @endif
    @if(session('import_errors'))
        <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded">
            <strong>Rows skipped:</strong>
            <ul class="list-disc list-inside mt-1">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Upload form --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <form action="{{ route('inventory.products.import.preview') }}" method="POST" enctype="multipart/form-data">
            @csrf

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

            <div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                <strong>Tip:</strong> Not sure of the format? Download the blank template, fill it in, and upload it back.
                <div class="mt-2">
                    <a href="{{ route('inventory.products.import.template') }}" class="text-[#6a0f70] underline text-xs">Download template</a>
                </div>
            </div>

            <div class="mb-5 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <strong>Scope:</strong> Clinical products only (no Saleable/FMCG items in this pass, no photos). Category, Sub Type, Variant and Vendor are matched by name and created automatically if they don't already exist.
            </div>

            <button type="submit"
                class="w-full bg-[#6a0f70] text-white py-2.5 text-sm font-medium hover:bg-[#380740] transition-colors rounded">
                Preview Import →
            </button>
        </form>
    </div>

    {{-- Sample sheet reference — shows the exact columns without needing to download first --}}
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-700 mb-1">Sample sheet</h2>
        <p class="text-xs text-gray-500 mb-3">The template workbook has two tabs. Column order doesn't matter — headers are matched by name.</p>

        <div class="mb-2 text-xs font-medium text-gray-600">Tab 1 — Products</div>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-5">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            @foreach(['Product Name*','Category','Sub Type','Variant','Brand','Alternative Brands','Company Name','Packaging Type*','Qty in Packaging*','Packaging Unit','Purchase Price','MRP','Minimum Stock Qty*','Reorder Level','Usage Type','Vendor Name','Treatment Tags','Description','Notes','Active'] as $col)
                                <th class="text-left px-3 py-2 text-gray-600 font-semibold whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach(['Filtek Z250 XT','Restorative Materials','Composite','A2 Shade','3M','Ivoclar, GC','3M ESPE','Syringe','4','g','850','','2','1','multiple_use','Prime Dental Supplies','Composite Filling, Aesthetic Dentistry','Universal composite','Keep refrigerated','Yes'] as $val)
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $val !== '' ? $val : '—' }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-5">* Required. Everything else can be left blank.</p>

        <div class="mb-2 text-xs font-medium text-gray-600">Tab 2 — Vendors <span class="text-gray-400 font-normal">(optional — skip if you have no supplier list yet)</span></div>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            @foreach(['Vendor Name*','Contact Person','Phone','WhatsApp','Email','GST No','Address','City','Credit Days','Active'] as $col)
                                <th class="text-left px-3 py-2 text-gray-600 font-semibold whitespace-nowrap">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach(['Prime Dental Supplies','Rajesh Kumar','9876543210','9876543210','sales@primedental.example','27AAAAA0000A1Z5','12 MG Road','Pune','30','Yes'] as $val)
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $val }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">* Required. A product row can reference a vendor by name — if it's new, it gets created here too.</p>
    </div>
</div>

<script>
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
