{{-- Clinical Tab — Filters + Results Table --}}
<div x-data="clinicalTab()" x-init="init()" style="padding:20px;">

    {{-- ── Filters ── --}}
    @include('content-management.partials.clinical.filters')

    {{-- ── Active filter pills ── --}}
    <div id="cms-active-filters" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;min-height:28px;align-items:center;">
        {{-- Injected by cms-search.js --}}
    </div>

    {{-- ── Results header ── --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <span id="cms-results-count" style="font-size:12px;color:#6b7280;">
            @if(isset($results))
                Showing {{ $results->firstItem() }}–{{ $results->lastItem() }} of {{ $results->total() }} results
            @else
                Loading results…
            @endif
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:11px;color:#9ca3af;">Sort by</span>
            <select id="cms-sort" onchange="window.cmsSearch?.sort(this.value)"
                    style="border:1px solid #e5e7eb;border-radius:4px;padding:4px 8px;font-size:12px;color:#374151;background:white;cursor:pointer;outline:none;">
                <option value="upload_date_desc">Start Date (Newest)</option>
                <option value="upload_date_asc">Start Date (Oldest)</option>
                <option value="patient_name">Patient Name</option>
                <option value="treatment_name">Treatment</option>
            </select>
        </div>
    </div>

    {{-- ── Results Table ── --}}
    <div id="cms-results-wrap">
        @include('content-management.partials.clinical.results-table')
    </div>

</div>

<script>
function clinicalTab() {
    return {
        init() {
            // On tab open, load results if not already loaded
        }
    }
}
</script>
