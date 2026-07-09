@extends('layouts.app')
@section('page-title', 'Smart Presentation — Shared Links')

@section('content')
<div class="p-6 space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-700">Smart Presentation</h1>
            <p class="text-sm text-gray-500 mt-0.5">Every link ever issued to a patient — revoke anything that shouldn't still be live.</p>
        </div>
    </div>

    @include('presentations.partials.tabs', ['active' => 'links'])

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Issued</th>
                    <th class="px-4 py-3">Expires</th>
                    <th class="px-4 py-3">Views</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tokens as $token)
                    @php $valid = $token->isValid(); @endphp
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $token->presentation?->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $token->created_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $token->expires_at?->format('d M Y') ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $token->view_count }}</td>
                        <td class="px-4 py-3">
                            @if($token->revoked_at)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Revoked</span>
                            @elseif(!$valid)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Expired</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($valid)
                            <form method="POST" action="{{ route('presentations.links.revoke', $token) }}" class="inline"
                                  onsubmit="return confirm('Revoke this link? The patient will no longer be able to open it.');">
                                @csrf
                                <button type="submit" class="text-red-500 hover:text-red-600 font-medium text-xs">Revoke</button>
                            </form>
                            @endif
                            @if($token->presentation)
                            <form method="POST" action="{{ route('presentations.links.regenerate', $token->presentation) }}" class="inline ml-2">
                                @csrf
                                <button type="submit" class="text-brand-600 hover:text-brand-700 font-medium text-xs">Regenerate</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No links issued yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tokens->links() }}
</div>
@endsection
