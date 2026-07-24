@push('styles')
<style>
    /* Patient profile — edge-to-edge content, no inner padding */
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }

    /* ── Sticky patient header ── */
    #patient-sticky-header {
        position: sticky;
        top: 0;          /* topbar is a flex sibling, not fixed — content area starts below it */
        z-index: 40;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(106,15,112,0.06);
    }

    .stat-card { transition: box-shadow 0.15s, transform 0.15s; }
    .stat-card:hover { box-shadow: 0 4px 16px rgba(106,15,112,0.10); transform: translateY(-1px); }
    .opp-dot { width:10px;height:10px;border-radius:50%;border:2px solid #e5e7eb;background:white;flex-shrink:0; }
    .opp-dot.active { border-color:currentColor;background:currentColor; }
    .opp-dot.passed { background:currentColor;border-color:currentColor;opacity:0.4; }
    .opp-line { flex:1;height:2px;background:#e5e7eb; }
    .opp-line.passed { opacity:0.4; }
    .timeline-icon { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .visit-row { transition:background 0.12s; }
    .visit-row:hover { background:#faf5ff; }
    .rapport-item { display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6; }
    .rapport-item:last-child { border-bottom:none; }
    .tag-pill { display:inline-flex;align-items:center;font-size:12px;padding:4px 12px;border-radius:999px;font-weight:500;border:1.5px solid transparent; }
    .quick-action-btn { display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 6px;border:1px solid #e5e7eb;background:white;cursor:pointer;transition:all 0.15s;border-radius:4px;text-align:center; }
    .quick-action-btn:hover { border-color:#6a0f70;background:#faf5ff; }
    .quick-action-btn svg { color:#6b7280; }
    .quick-action-btn:hover svg { color:#6a0f70; }
    .quick-action-btn span { font-size:10px;color:#6b7280;line-height:1.3;font-weight:500; }
    .quick-action-btn:hover span { color:#6a0f70; }
    .opp-card { min-width:220px;max-width:220px;border:1px solid #e5e7eb;border-radius:6px;padding:14px;background:white;flex-shrink:0;transition:border-color 0.15s; }
    .opp-card:hover { border-color:#6a0f70; }
    .scroll-area { display:flex;gap:12px;overflow-x:auto;padding-bottom:6px;scrollbar-width:none; }
    .scroll-area::-webkit-scrollbar { display:none; }
    .section-title { font-size:12px;font-weight:700;color:#5b21b6;letter-spacing:0.06em;text-transform:uppercase; }

    /* Consultation screen styles */
    .consult-card { background:white;border:1px solid #e5e7eb;border-radius:8px;padding:20px;transition:all 0.15s; }
    .consult-card:hover { border-color:#6a0f70;box-shadow:0 2px 12px rgba(106,15,112,0.08); }
    .consult-entry { border-left:3px solid #e5e7eb;padding-left:16px;position:relative; }
    .consult-entry::before { content:'';position:absolute;left:-6px;top:6px;width:10px;height:10px;border-radius:50%;background:#6a0f70;border:2px solid white;box-shadow:0 0 0 2px #6a0f70; }
    .consult-section-label { font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:6px; }

    /* ── Patient profile — capsule tab nav ───────────────────── */
    .patient-tab-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 5px 6px;
        background: #f0e6f2;
        border-radius: 12px;
        overflow-x: auto;
        scrollbar-width: none;
        flex-wrap: nowrap;
    }
    .patient-tab-nav::-webkit-scrollbar { display: none; }
    .patient-tab-btn {
        flex-shrink: 0;
        padding: 6px 14px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s, box-shadow 0.15s;
        line-height: 1.4;
    }
    .patient-tab-btn:hover {
        background: rgba(106,15,112,0.08);
        color: #6a0f70;
    }
    .patient-tab-btn.active {
        background: #ffffff;
        color: #6a0f70;
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(106,15,112,0.15), 0 0 0 1px rgba(106,15,112,0.10);
    }
</style>
@endpush
