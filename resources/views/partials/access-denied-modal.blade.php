{{--
|==========================================================================
| Access Denied Modal
| File: resources/views/partials/access-denied-modal.blade.php
|
| Permission-gate middleware (EnsureAdminRole, CheckModulePermission,
| CommunicationModuleAccess) no longer abort(403) to a separate error
| page. Instead they redirect back and flash session('access_denied')
| with the reason. This partial renders that flash as a blocking popup
| on whatever page the user lands on, so navigation never leaves the app.
|==========================================================================
--}}
@if (session('access_denied'))
    <div
        id="df-access-denied-overlay"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="df-access-denied-title"
        style="
            position:fixed; inset:0; z-index:300;
            background:rgba(14,1,24,0.55);
            display:flex; align-items:center; justify-content:center;
            padding:16px;
        "
    >
        <div
            style="
                background:var(--df-surface,#ffffff);
                border-radius:6px;
                max-width:380px; width:100%;
                padding:28px 24px 24px;
                box-shadow:0 12px 40px rgba(14,1,24,0.25);
                text-align:center;
                font-family:'DM Sans', sans-serif;
            "
        >
            <div style="width:44px;height:44px;border-radius:50%;background:#fdeaea;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#b52020" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <h2 id="df-access-denied-title" style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600;color:var(--df-text,#1a0a24);margin:0 0 8px;">
                Access Denied
            </h2>
            <p style="font-size:13px;color:var(--df-text-muted,#7a6884);line-height:1.55;margin:0 0 20px;">
                {{ session('access_denied') }}
            </p>
            <button
                type="button"
                onclick="document.getElementById('df-access-denied-overlay').remove()"
                style="
                    background:var(--df-color-primary,#6a0f70);
                    color:#fff; border:none; border-radius:4px;
                    padding:9px 26px; font-size:13px; font-weight:500;
                    cursor:pointer;
                "
            >
                Got it
            </button>
        </div>
    </div>
    <script>
        (function () {
            document.addEventListener('keydown', function onDfAccessDeniedEsc(e) {
                if (e.key === 'Escape') {
                    var el = document.getElementById('df-access-denied-overlay');
                    if (el) el.remove();
                    document.removeEventListener('keydown', onDfAccessDeniedEsc);
                }
            });
        })();
    </script>
@endif
