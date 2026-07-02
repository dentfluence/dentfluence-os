@extends('layouts.app')
@section('page-title', 'Issue Memo')

@section('content')
<div class="p-6 max-w-2xl mx-auto space-y-6">

    @include('hr.partials.subnav', ['active' => 'memos'])

    <div class="flex items-center gap-3">
        <a href="{{ route('hr.memos.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-2xl font-display font-semibold text-gray-900">Issue Performance Memo</h1>
    </div>

    <form action="{{ route('hr.memos.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">

            {{-- Staff --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Staff Member <span class="text-red-500">*</span></label>
                <select name="staff_user_id" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    <option value="">— Select staff —</option>
                    @foreach($staff as $s)
                    <option value="{{ $s->id }}" {{ old('staff_user_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
                @error('staff_user_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Type + Date --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Memo Type <span class="text-red-500">*</span></label>
                    <select name="type" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        <option value="praise"      {{ old('type') == 'praise'      ? 'selected' : '' }}>Praise</option>
                        <option value="warning"     {{ old('type') == 'warning'     ? 'selected' : '' }}>Warning</option>
                        <option value="improvement" {{ old('type') == 'improvement' ? 'selected' : '' }}>Improvement Plan</option>
                        <option value="review"      {{ old('type') == 'review'      ? 'selected' : '' }}>Performance Review</option>
                        <option value="general"     {{ old('type') == 'general'     ? 'selected' : '' }}>General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Memo Date <span class="text-red-500">*</span></label>
                    <input type="date" name="memo_date" value="{{ old('memo_date', today()->toDateString()) }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                </div>
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                    placeholder="e.g. Punctuality Issue — June 2026">
                @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Body --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Memo Body <span class="text-red-500">*</span></label>
                <textarea name="body" rows="8" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-y"
                    placeholder="Write the full memo here. Be specific about dates, incidents, and expected behaviour...">{{ old('body') }}</textarea>
                @error('body')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Confidential --}}
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_confidential" value="0">
                    <input type="checkbox" name="is_confidential" value="1"
                        {{ old('is_confidential') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-purple-600 focus:ring-purple-300">
                    <span class="text-sm text-gray-700">Mark as confidential (only HR/Admin can view)</span>
                </label>
            </div>

        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('hr.memos.index') }}"
               class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </a>
            <button type="submit"
                class="px-5 py-2.5 text-sm font-medium text-white bg-purple-700 rounded-lg hover:bg-purple-800 transition">
                Issue Memo
            </button>
        </div>
    </form>

</div>
@endsection
