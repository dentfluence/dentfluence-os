@extends('layouts.app')
@section('page-title', 'Smart Presentation — Settings')

@section('content')
<div class="p-6 space-y-6 max-w-2xl">

    <div>
        <h1 class="text-2xl font-display font-semibold text-brand-700">Smart Presentation</h1>
        <p class="text-sm text-gray-500 mt-0.5">Module settings.</p>
    </div>

    @include('presentations.partials.tabs', ['active' => 'settings'])

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('presentations.settings.update') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5 block">Default link expiry</label>
            <div class="flex items-center gap-2">
                <input type="number" name="default_expiry_days" min="0" max="365"
                    value="{{ old('default_expiry_days', $defaultExpiryDays) }}"
                    class="w-24 rounded-lg border border-gray-200 text-sm p-2 text-gray-700">
                <span class="text-sm text-gray-500">days (0 = never expires by default)</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Applies to new links going forward. A dentist can still extend or revoke any individual link from Shared Links.
            </p>
        </div>

        <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
            Save Settings
        </button>
    </form>
</div>
@endsection
