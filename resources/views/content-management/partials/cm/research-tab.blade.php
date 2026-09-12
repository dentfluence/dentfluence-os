{{--
    ══════════════════════════════════════════════════════
    CONTENT MANAGER — RESEARCH TAB

    Same story as Teaching: a placeholder that claimed "3 files" and carried a
    "Full research workflow coming in a future update" badge. The files were
    always real; only this screen was not.
    ══════════════════════════════════════════════════════
--}}
@include('content-management.partials.cm._lane-grid', [
    'lane'      => 'Research',
    'emptyHint' => 'Tag a clinical file as Research Eligible and it appears here. Patient identity is never shown in this view.',
])
