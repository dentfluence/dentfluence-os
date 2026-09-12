{{--
    ══════════════════════════════════════════════════════
    CONTENT MANAGER — TEACHING TAB

    This used to be a static placeholder announcing "7 files are currently
    tagged as teaching-eligible" with a dead "View them →" link, while the tab
    badge directly above it showed the real count. Two contradicting numbers on
    one screen, neither of them coming from the database.

    It renders the same grid as every other lane now, from $files.
    ══════════════════════════════════════════════════════
--}}
@include('content-management.partials.cm._lane-grid', [
    'lane'      => 'Teaching',
    'emptyHint' => 'Tag a clinical file as Teaching Eligible — in the File Viewer, or while uploading — and it appears here.',
])
