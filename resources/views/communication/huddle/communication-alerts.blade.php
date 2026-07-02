{{--
    communication/huddle/communication-alerts.blade.php
    Renders each communication alert as an hd-stat-pill — same style as Critical Alerts.

    Required variables:
        $alerts  — array from HuddleController::buildAlerts()
        $counts  — array from HuddleController::buildCounts()
--}}

@foreach($alerts as $alert)
@php $tag = !empty($alert['link']) ? 'a' : 'div'; @endphp
<{{ $tag }}
    class="hd-stat-pill {{ isset($alert['urgent']) && $alert['urgent'] && $alert['count'] > 0 ? 'hd-stat-pill--urgent' : '' }}"
    @if(!empty($alert['link']))
        href="{{ $alert['link'] }}"
        style="text-decoration:none;color:inherit;cursor:pointer;"
    @endif>
    <div>
        <div class="hd-stat-val"
             style="{{ isset($alert['urgent']) && $alert['urgent'] && $alert['count'] > 0 ? 'color:'.$alert['color'].';' : '' }}">
            {{ $alert['count'] }}
        </div>
        <div class="hd-stat-label">{{ $alert['title'] }}</div>
        @if(!empty($alert['names']))
            <div class="hd-stat-sub" style="color:{{ $alert['color'] }};">
                {{ implode(', ', array_slice($alert['names'], 0, 1)) }}
                @if(count($alert['names']) > 1)
                    +{{ count($alert['names']) - 1 }} more
                @endif
            </div>
        @else
            <div class="hd-stat-sub" style="color:var(--c-muted);">{{ $alert['action'] }}</div>
        @endif
    </div>
</{{ $tag }}>
@endforeach
