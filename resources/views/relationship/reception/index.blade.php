{{--
|==========================================================================
| PRE — Reception Dashboard (Phase 1 · Workstream E, slice E3)
| Route: GET /relationship/reception   [relationship.reception]
|
| Read-only. Reads the Today's Actions projection into two queues.
| Variables from ReceptionController@index:
|   $calls, $work, $summary, $generatedAt, $highCount
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Reception')

@php
    use Illuminate\Support\Carbon;

    $renderItem = function (array $it) {
        $link  = $it['link'] ?? '#';
        $name  = $it['patient_name'] ?? 'Unknown';
        $phone = $it['meta']['phone'] ?? null;
        $prio  = $it['priority'] ?? 'low';
        $pc = match ($prio) {
            'high'   => ['#8A1F1F', '#FDECEC'],
            'medium' => ['#854F0B', '#FAEEDA'],
            default  => ['#4b5563', '#f0f1f3'],
        };
        return [
            'link' => $link, 'name' => $name, 'phone' => $phone,
            'reason' => $it['reason'] ?? '', 'label' => $it['category_label'] ?? '',
            'prio' => $prio, 'pc' => $pc,
        ];
    };
@endphp

@section('content')
<div style="max-width:1200px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;">Reception</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Everything to call and do today, from the Today's Actions view.
                @if ($generatedAt)
                    <span style="color:#9ca3af;">· updated {{ Carbon::parse($generatedAt)->diffForHumans() }}</span>
                @else
                    <span style="color:#b45309;">· projection not built yet — run <code>today:rebuild-projection</code></span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('relationship.today') }}"
               style="background:#534AB7;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Full Today's Actions
            </a>
            <a href="{{ route('relationship.dashboard') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Relationships
            </a>
        </div>
    </div>

    {{-- Summary tiles --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:22px;">
        @php
            $tiles = [
                ['label' => 'Total actions', 'value' => $summary['total'] ?? 0,     'color' => '#534AB7', 'bg' => '#EEEDFE'],
                ['label' => 'High priority', 'value' => $highCount,                  'color' => '#8A1F1F', 'bg' => '#FDECEC'],
                ['label' => 'Calls',         'value' => count($calls),               'color' => '#0F6E56', 'bg' => '#E1F5EE'],
                ['label' => 'Other work',    'value' => count($work),                'color' => '#185FA5', 'bg' => '#E6F1FB'],
            ];
        @endphp
        @foreach ($tiles as $t)
            <div style="background:{{ $t['bg'] }};border-radius:12px;padding:16px 18px;">
                <div style="font-size:26px;font-weight:800;color:{{ $t['color'] }};line-height:1;">{{ number_format($t['value']) }}</div>
                <div style="margin-top:6px;color:#4b5563;font-size:13px;font-weight:600;">{{ $t['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Two queues --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;">

        @php
            $columns = [
                ['title' => "Today's Calls", 'items' => $calls, 'empty' => 'No calls queued.'],
                ['title' => "Today's Work",  'items' => $work,  'empty' => 'No other tasks today.'],
            ];
        @endphp

        @foreach ($columns as $col)
            <div style="background:#fff;border:1px solid #eceef2;border-radius:12px;overflow:hidden;">
                <div style="padding:13px 16px;border-bottom:1px solid #f1f2f4;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-weight:700;color:#1f2937;font-size:14px;">{{ $col['title'] }}</span>
                    <span style="background:#f0f1f3;color:#6b7280;font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:999px;">{{ count($col['items']) }}</span>
                </div>

                @if (empty($col['items']))
                    <div style="padding:22px 16px;color:#9ca3af;font-size:13px;">{{ $col['empty'] }}</div>
                @else
                    <div style="display:flex;flex-direction:column;">
                        @foreach ($col['items'] as $raw)
                            @php $it = $renderItem($raw); @endphp
                            <a href="{{ $it['link'] }}" style="display:block;padding:12px 16px;border-top:1px solid #f4f5f7;text-decoration:none;">
                                <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">
                                    <span style="font-weight:600;color:#1f2937;font-size:13.5px;">{{ $it['name'] }}</span>
                                    <span style="flex-shrink:0;font-size:10.5px;padding:1px 8px;border-radius:999px;background:{{ $it['pc'][1] }};color:{{ $it['pc'][0] }};">{{ $it['label'] }}</span>
                                </div>
                                <div style="margin-top:3px;color:#6b7280;font-size:12px;">{{ $it['reason'] }}</div>
                                @if ($it['phone'])
                                    <div style="margin-top:2px;color:#9ca3af;font-size:11.5px;">{{ $it['phone'] }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <p style="margin-top:16px;color:#9ca3af;font-size:12px;">
        PRE (Relationship Platform) · read-only, served from the Today's Actions projection (one view, no live 12-domain reads).
    </p>
</div>
@endsection
