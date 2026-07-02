@extends('layouts.app')
@section('page-title', $memo->subject)

@section('content')
<div class="p-6 max-w-3xl mx-auto space-y-6">

    @include('hr.partials.subnav', ['active' => 'memos'])

    <div class="flex items-center gap-3">
        <a href="{{ route('hr.memos.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-2xl font-display font-semibold text-gray-900">Performance Memo</h1>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Memo Card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">

        {{-- Header --}}
        <div class="p-6 border-b border-gray-50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold {{ $memo->typeBadgeClass() }}">
                            {{ $memo->typeLabel() }}
                        </span>
                        @if($memo->is_confidential)
                        <span class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-lg">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            Confidential
                        </span>
                        @endif
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $memo->subject }}</h2>
                </div>
                <form action="{{ route('hr.memos.destroy', $memo) }}" method="POST" class="flex-shrink-0">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this memo permanently?')"
                        class="text-xs text-gray-400 hover:text-red-500 transition px-3 py-1.5 border border-gray-200 rounded-lg hover:border-red-200">
                        Delete
                    </button>
                </form>
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">To</dt>
                    <dd class="text-gray-900 font-medium mt-0.5">{{ $memo->staff?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Issued By</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $memo->issuedBy?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $memo->memo_date->format('d M Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Body --}}
        <div class="p-6">
            <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap leading-relaxed">
                {{ $memo->body }}
            </div>
        </div>

        {{-- Acknowledgement --}}
        <div class="p-6 border-t border-gray-50 flex items-center justify-between">
            <div>
                @if($memo->staff_acknowledged)
                <div class="flex items-center gap-2 text-green-600 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Acknowledged by {{ $memo->staff?->name }} on {{ $memo->acknowledged_at?->format('d M Y h:i A') }}
                </div>
                @else
                <p class="text-sm text-gray-500">Awaiting acknowledgement from {{ $memo->staff?->name }}</p>
                @endif
            </div>

            {{-- Allow the staff member themselves (or admin) to acknowledge --}}
            @if(!$memo->staff_acknowledged)
            <form action="{{ route('hr.memos.acknowledge', $memo) }}" method="POST">
                @csrf
                <button type="submit"
                    class="px-4 py-2 bg-purple-700 text-white text-sm font-medium rounded-lg hover:bg-purple-800 transition">
                    Mark as Acknowledged
                </button>
            </form>
            @endif
        </div>

    </div>

</div>
@endsection
