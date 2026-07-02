@extends('layouts.app')
@section('page-title', 'Performance Memos')

@section('content')
<div class="p-6 space-y-6" x-data="{ showCreate: false }">

    @include('hr.partials.subnav', ['active' => 'memos'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">Performance Memos</h1>
            <p class="text-sm text-gray-500 mt-0.5">Issue and track staff memos, warnings, and praise</p>
        </div>
        <button @click="showCreate = true"
            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-700 rounded-lg text-sm font-medium text-white hover:bg-purple-800 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Issue Memo
        </button>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- ══════════════════════════════════════════
         ISSUE MEMO MODAL
    ══════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="showCreate" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40"
         @keydown.escape.window="showCreate = false"
         @click.self="showCreate = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
                <h2 class="font-semibold text-gray-900 text-lg">Issue Performance Memo</h2>
                <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('hr.memos.store') }}" method="POST" class="p-6 space-y-5">
                @csrf

                {{-- Staff --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff Member <span class="text-red-500">*</span></label>
                    <select name="staff_user_id" required autofocus
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
                    <textarea name="body" rows="7" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-y"
                        placeholder="Be specific about dates, incidents, and expected behaviour...">{{ old('body') }}</textarea>
                    @error('body')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Confidential --}}
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_confidential" value="0">
                    <input type="checkbox" name="is_confidential" value="1"
                        {{ old('is_confidential') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-purple-600 focus:ring-purple-300">
                    <span class="text-sm text-gray-600">Mark as confidential (only HR/Admin can view)</span>
                </label>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-50">
                    <button type="button" @click="showCreate = false"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-purple-700 rounded-lg hover:bg-purple-800 transition">
                        Issue Memo
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>{{-- /x-teleport --}}

    {{-- ══════════════════════════════════════════
         FILTERS
    ══════════════════════════════════════════ --}}
    <form method="GET" class="flex gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Staff</label>
            <select name="staff_id" class="border border-gray-200 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-purple-300 outline-none">
                <option value="">All Staff</option>
                @foreach($staff as $s)
                <option value="{{ $s->id }}" {{ request('staff_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
            <select name="type" class="border border-gray-200 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-purple-300 outline-none">
                <option value="">All Types</option>
                <option value="praise"      {{ request('type') == 'praise'      ? 'selected' : '' }}>Praise</option>
                <option value="warning"     {{ request('type') == 'warning'     ? 'selected' : '' }}>Warning</option>
                <option value="improvement" {{ request('type') == 'improvement' ? 'selected' : '' }}>Improvement Plan</option>
                <option value="review"      {{ request('type') == 'review'      ? 'selected' : '' }}>Performance Review</option>
                <option value="general"     {{ request('type') == 'general'     ? 'selected' : '' }}>General</option>
            </select>
        </div>
        <button type="submit"
            class="px-4 py-2 text-sm bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition">Filter</button>
        @if(request()->hasAny(['staff_id', 'type']))
        <a href="{{ route('hr.memos.index') }}" class="text-xs text-gray-400 hover:underline self-center">Clear</a>
        @endif
    </form>

    {{-- ══════════════════════════════════════════
         MEMOS LIST
    ══════════════════════════════════════════ --}}
    @if($memos->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm text-center py-16 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm">No memos yet.</p>
        <button @click="showCreate = true" class="text-purple-600 text-sm hover:underline mt-1 inline-block">Issue the first memo →</button>
    </div>
    @else
    <div class="space-y-3">
        @foreach($memos as $memo)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:border-purple-200 transition">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4 flex-1 min-w-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold flex-shrink-0 {{ $memo->typeBadgeClass() }}">
                        {{ $memo->typeLabel() }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $memo->subject }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            To: <span class="font-medium text-gray-700">{{ $memo->staff?->name }}</span>
                            · Issued by {{ $memo->issuedBy?->name }}
                            · {{ $memo->memo_date->format('d M Y') }}
                        </p>
                        <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $memo->body }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($memo->is_confidential)
                    <span class="text-xs text-gray-400 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Confidential
                    </span>
                    @endif
                    @if($memo->staff_acknowledged)
                    <span class="text-xs text-green-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Acknowledged
                    </span>
                    @else
                    <span class="text-xs text-yellow-600">Pending ack.</span>
                    @endif
                    <a href="{{ route('hr.memos.show', $memo) }}"
                       class="text-sm text-purple-600 hover:underline font-medium ml-2">View →</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $memos->links() }}</div>
    @endif

</div>
@endsection
