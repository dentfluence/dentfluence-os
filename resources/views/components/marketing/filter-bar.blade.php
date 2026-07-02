{{--
|==========================================================================
| Component: filter-bar
| Usage:
|   <x-marketing.filter-bar
|       search_placeholder="Search campaigns..."
|       :filters="[
|           ['label' => 'Status', 'name' => 'status', 'options' => [
|               ['value' => '', 'label' => 'All Statuses'],
|               ['value' => 'running', 'label' => 'Running'],
|               ['value' => 'draft',   'label' => 'Draft'],
|           ]],
|           ['label' => 'Platform', 'name' => 'platform', 'options' => [
|               ['value' => '', 'label' => 'All Platforms'],
|               ['value' => 'instagram', 'label' => 'Instagram'],
|           ]],
|       ]"
|   />
|
| Props:
|   search_placeholder — placeholder text for search input
|   filters            — array of filter groups, each with: label, name, options[]
|                        Each option: ['value' => '...', 'label' => '...']
|==========================================================================
--}}
@props([
    'search_placeholder' => 'Search...',
    'filters'            => [],
])

<div style="
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-family: 'Inter', sans-serif;
">

    {{-- ── Search input ── --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 7px;
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.20);
        border-radius: 7px;
        padding: 0 10px;
        height: 36px;
        min-width: 220px;
        flex: 1;
        max-width: 340px;
    ">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="{{ $search_placeholder }}"
            style="
                border: none;
                background: transparent;
                outline: none;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                color: #1e0a2c;
                width: 100%;
            "
            aria-label="{{ $search_placeholder }}"
        >
    </div>

    {{-- ── Dropdown filters ── --}}
    @foreach($filters as $filter)
    <div style="position: relative;">
        <select
            name="{{ $filter['name'] }}"
            style="
                appearance: none;
                -webkit-appearance: none;
                background: #ffffff;
                border: 1px solid rgba(185,92,183,0.20);
                border-radius: 7px;
                padding: 0 30px 0 11px;
                height: 36px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                color: {{ request($filter['name']) ? '#1e0a2c' : '#9b6aad' }};
                cursor: pointer;
                outline: none;
                min-width: 130px;
            "
            aria-label="{{ $filter['label'] }}"
        >
            @foreach($filter['options'] as $option)
            <option
                value="{{ $option['value'] }}"
                {{ request($filter['name']) == $option['value'] ? 'selected' : '' }}
            >{{ $option['label'] }}</option>
            @endforeach
        </select>
        {{-- Chevron --}}
        <svg
            width="12" height="12"
            viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round"
            style="
                position: absolute;
                right: 9px;
                top: 50%;
                transform: translateY(-50%);
                pointer-events: none;
            "
        >
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </div>
    @endforeach

    {{-- ── Clear filters (shown only when any filter is active) ── --}}
    @if(request()->hasAny(array_merge(['search'], array_column($filters, 'name'))))
    <a
        href="{{ url()->current() }}"
        style="
            display: inline-flex;
            align-items: center;
            gap: 4px;
            height: 36px;
            padding: 0 10px;
            border-radius: 7px;
            border: 1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.06);
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 500;
            color: #dc2626;
            text-decoration: none;
            white-space: nowrap;
        "
        title="Clear all filters"
    >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
        Clear
    </a>
    @endif

</div>
