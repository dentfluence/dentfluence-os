{{--
    Kanban board partial.
    Variables: $stages (array), $leads (array grouped by stage id)
--}}
<div class="prm-board" id="prmBoard">
    @foreach($stages as $stage)
    <div class="prm-column"
         data-stage="{{ $stage['id'] }}"
         id="column-{{ $stage['id'] }}"
         ondragover="event.preventDefault(); PrmBoard.onDragOver(this)"
         ondrop="PrmBoard.onDrop(event, '{{ $stage['id'] }}')"
         ondragleave="PrmBoard.onDragLeave(this)">

        {{-- Column Header --}}
        <div class="prm-column__header">
            <div class="prm-column__header-left">
                <span class="prm-column__dot" style="background:{{ $stage['color'] }}"></span>
                <span class="prm-column__label">{{ $stage['label'] }}</span>
            </div>
            <span class="prm-column__count" style="background:{{ $stage['bg'] }};color:{{ $stage['color'] }}">
                {{ count($leads[$stage['id']] ?? []) }}
            </span>
        </div>

        {{-- Lead Cards --}}
        <div class="prm-column__cards" id="cards-{{ $stage['id'] }}">
            @forelse($leads[$stage['id']] ?? [] as $lead)
                @include('components.prm.lead-card', ['lead' => $lead, 'stage' => $stage])
            @empty
                <div class="prm-column__empty">No leads in this stage</div>
            @endforelse
        </div>

        {{-- Add Lead / View All footer --}}
        @if($stage['id'] === 'converted')
        <a href="{{ route('communication.prm.index') }}?stage=converted" class="prm-column__view-all">
            View All ({{ count($leads[$stage['id']] ?? []) }}) →
        </a>
        @else
        <button class="prm-column__add-btn" data-stage="{{ $stage['id'] }}" data-action="open-add-lead"
            style="color:{{ $stage['color'] }};border-color:{{ $stage['color'] }}22">
            <i class="ti ti-plus"></i> Add Lead
        </button>
        @endif

    </div>
    @endforeach
</div>
