{{-- Component: pipeline-column  Usage: <x-prm.pipeline-column :stage="$stage" :leads="$leads" /> --}}
@props([
    'stageKey'   => 'new_lead',
    'stageLabel' => 'New Lead',
    'color'      => '#185FA5',
    'leads'      => collect(),
    'totalCount' => 0,
])

@php
$showCount = $totalCount ?: $leads->count();
$colorMap  = [
    'new_lead'     => 'cc-blue',
    'contacted'    => 'cc-teal',
    'appointment'  => 'cc-amber',
    'consultation' => 'cc-purple',
    'plan_given'   => 'cc-green',
    'converted'    => 'cc-teal-dark',
    'lost'         => 'cc-gray',
];
$countClass = $colorMap[$stageKey] ?? 'cc-blue';
$maxVisible = 5;
$visible    = $leads->take($maxVisible);
$remaining  = max(0, $showCount - $maxVisible);
@endphp

<div class="pipeline-col"
     data-stage="{{ $stageKey }}"
     ondragover="event.preventDefault(); this.classList.add('drag-over')"
     ondragleave="this.classList.remove('drag-over')"
     ondrop="onDropLead(event, '{{ $stageKey }}')">

    <div class="col-accent" style="background: {{ $color }}"></div>

    <div class="col-head">
        <span class="col-name">{{ $stageLabel }}</span>
        <span class="col-count {{ $countClass }}">{{ $showCount }}</span>
    </div>

    <div class="col-cards" id="col-cards-{{ $stageKey }}">
        @forelse($visible as $lead)
            <x-prm.lead-card :lead="$lead" />
        @empty
            <div class="col-empty">
                <i class="ti ti-inbox" aria-hidden="true"></i>
                <span>No leads</span>
            </div>
        @endforelse
    </div>

    @if($remaining > 0)
        <a href="/communication/prm?stage={{ $stageKey }}"
           class="col-view-all">
            View all {{ $showCount }} <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
    @endif

    <div class="col-add-btn"
         onclick="window.location='/communication/prm/add-lead?stage={{ $stageKey }}'">
        @if($stageKey === 'converted')
            <i class="ti ti-eye" aria-hidden="true"></i>
            View All ({{ $showCount }})
        @else
            <i class="ti ti-plus" aria-hidden="true"></i>
            Add Lead
        @endif
    </div>

</div>
