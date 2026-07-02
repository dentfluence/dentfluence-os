@extends('layouts.app')
@section('page-title', 'Notifications')

@section('content')
<div class="p-4 md:p-6 max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 style="font-size:20px;font-weight:600;color:#1a0a24;margin:0 0 2px;">Notifications</h1>
            <p style="font-size:13px;color:#9e8fa0;margin:0;">All recent activity and alerts</p>
        </div>
        @if($notifications->total() > 0)
        <form method="POST" action="{{ route('notifications.markAllRead') }}" class="inline">
            @csrf
            <button type="submit"
                    style="font-size:12px;font-weight:500;color:#6a0f70;background:none;border:1px solid rgba(106,15,112,0.25);border-radius:6px;padding:6px 14px;cursor:pointer;transition:background 150ms;"
                    onmouseover="this.style.background='#f9f3fa';"
                    onmouseout="this.style.background='none';">
                ✓ Mark all read
            </button>
        </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        {{-- Empty state --}}
        <div style="text-align:center;padding:64px 24px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d4c8dc" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 16px;">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <p style="font-size:15px;font-weight:500;color:#2a1440;margin:0 0 6px;">All caught up!</p>
            <p style="font-size:13px;color:#9e8fa0;margin:0;">No notifications to show.</p>
        </div>
    @else
        {{-- Notification list --}}
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:10px;overflow:hidden;">
            @foreach($notifications as $n)
            @php
            $typeColor = match($n->type) {
                'appointment' => '#6a0f70',
                'lab'         => '#0070b0',
                'inventory'   => '#a05c00',
                'payment'     => '#b52020',
                default       => '#5a5a7a',
            };
            $typeBg = match($n->type) {
                'appointment' => '#f5eafb',
                'lab'         => '#e8f3fb',
                'inventory'   => '#fdf4e8',
                'payment'     => '#fdeaea',
                default       => '#f5f5f8',
            };
            @endphp
            <div style="display:flex;gap:14px;padding:14px 18px;border-bottom:1px solid #f5eef8;{{ $n->is_read ? '' : 'background:#fdf8ff;' }}">

                {{-- Type icon --}}
                <div style="flex-shrink:0;width:36px;height:36px;border-radius:8px;background:{{ $typeBg }};display:flex;align-items:center;justify-content:center;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $typeColor }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        {!! $n->icon !!}
                    </svg>
                </div>

                {{-- Content --}}
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                        <p style="font-size:13.5px;font-weight:{{ $n->is_read ? '400' : '600' }};color:#1a0a24;margin:0 0 3px;line-height:1.4;">
                            {{ $n->title }}
                        </p>
                        @if(!$n->is_read)
                            <span style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:{{ $typeColor }};margin-top:5px;"></span>
                        @endif
                    </div>
                    @if($n->message)
                        <p style="font-size:12.5px;color:#7a6884;margin:0 0 5px;line-height:1.45;">{{ $n->message }}</p>
                    @endif
                    <div style="display:flex;align-items:center;gap:14px;">
                        <span style="font-size:11.5px;color:#b0a4bc;">{{ $n->created_at->diffForHumans() }}</span>
                        @if($n->action_url)
                            <a href="{{ $n->action_url }}"
                               style="font-size:11.5px;font-weight:500;color:#6a0f70;text-decoration:none;"
                               onmouseover="this.style.textDecoration='underline';"
                               onmouseout="this.style.textDecoration='none';">
                                {{ $n->action_label ?? 'View' }} →
                            </a>
                        @endif
                        <span style="font-size:11px;color:#c8bcd4;text-transform:capitalize;">{{ $n->type }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
        @endif
    @endif

</div>
@endsection
