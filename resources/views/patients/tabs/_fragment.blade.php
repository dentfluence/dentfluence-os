{{-- Phase 4 lazy-tab fragment wrapper (no layout).
     Renders one tab body, then flushes any @push()ed styles/scripts the tab
     partials registered — without this, tabs like Treatment Plan / Visits /
     Lab would lose their JS when served as a fragment. --}}
@include('patients.tabs.' . $tab)
@stack('styles')
@stack('scripts')
