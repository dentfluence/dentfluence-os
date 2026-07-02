@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('settings.index', ['tab' => 'data']) }}" class="text-sm text-gray-500 hover:text-[#6a0f70] flex items-center gap-1 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back — re-upload
        </a>
        <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Preview Import</h1>
        <p class="text-sm text-gray-500 mt-1">
            Showing first 10 of <strong>{{ $totalRows }}</strong> rows from
            <span class="capitalize font-medium">{{ $source }}</span> format.
        </p>
    </div>

    {{-- Stats bar --}}
    <div class="flex gap-4 mb-5">
        <div class="flex-1 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-green-700">{{ $totalRows }}</div>
            <div class="text-xs text-green-600 mt-0.5">Total Rows</div>
        </div>
        <div class="flex-1 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-amber-700">{{ $duplicates }}</div>
            <div class="text-xs text-amber-600 mt-0.5">Existing (phone match)</div>
        </div>
        <div class="flex-1 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-blue-700">{{ $totalRows - $duplicates }}</div>
            <div class="text-xs text-blue-600 mt-0.5">New Patients</div>
        </div>
    </div>

    {{-- Preview table --}}
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-5">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        @foreach(['Patient ID','Name','Mobile No.','Email ID','DOB','Age','Gender','Address','Medical History'] as $col)
                            <th class="text-left px-3 py-2 text-gray-600 font-semibold whitespace-nowrap text-xs">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($preview as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-[#6a0f70] font-mono text-xs font-semibold whitespace-nowrap">{{ $row['patient_id'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800 font-medium max-w-[140px] truncate">
                                {{ $row['name'] ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['phone'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[140px] truncate">{{ $row['email'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['date_of_birth'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['age_years'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 capitalize whitespace-nowrap">{{ $row['gender'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[140px] truncate">{{ $row['address'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[150px] truncate">{{ $row['chief_complaint'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">No rows parsed — check your file format.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($totalRows > 10)
            <div class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                … and {{ $totalRows - 10 }} more rows (not shown)
            </div>
        @endif
    </div>

    {{-- Confirm form --}}
    <form action="{{ route('settings.data.import.store') }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-5">
        @csrf

        @if($duplicates > 0)
            <div class="mb-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="skip_duplicates" value="1" checked
                        class="w-4 h-4 rounded border-gray-300 text-[#6a0f70] focus:ring-[#6a0f70]">
                    <span class="text-sm text-gray-700">
                        Skip <strong>{{ $duplicates }}</strong> duplicate(s) — patients whose phone number already exists
                    </span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-7">Uncheck to import them anyway as new records.</p>
            </div>
        @endif

        <div class="flex gap-3">
            <a href="{{ route('settings.index', ['tab' => 'data']) }}"
                class="flex-1 text-center border border-gray-300 text-gray-700 py-2.5 text-sm font-medium hover:bg-gray-50 transition-colors rounded">
                ← Cancel
            </a>
            <button type="submit"
                class="flex-2 flex-1 bg-[#6a0f70] text-white py-2.5 px-6 text-sm font-medium hover:bg-[#380740] transition-colors rounded">
                Import {{ $totalRows - ($duplicates) }} Patient{{ ($totalRows - $duplicates) !== 1 ? 's' : '' }}
            </button>
        </div>
    </form>
</div>
@endsection
