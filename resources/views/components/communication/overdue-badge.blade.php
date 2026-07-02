{{--
  Overdue Badge Component
  Usage: <x-communication.overdue-badge :since="$item['overdue_since']" />
--}}
@props(['since' => null])

@if($since)
<span class="cm-overdue-badge">
    Overdue {{ $since }}
</span>
@endif
