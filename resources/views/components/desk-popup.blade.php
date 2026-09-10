{{--
    components/desk-popup.blade.php — N-3 web popup layer (2026-09-09)

    The front-desk popup. Polls GET /notifications/popups every 4 seconds
    (only while the tab is visible) and shows every popup-level notification
    this user has not yet answered as a card pinned to the top of the screen.
    The card stays until the person presses DONE (clears it for everyone who
    received it) or LATER (drops this user's copy to the bell). A short two-
    tone chime plays the FIRST time a card appears in this browser session;
    a page reload never re-chimes the same card.

    Deliberately NOT a blocking modal: reception is mid-typing when the
    doctor saves, and the card's own action button navigates away — a
    backdrop that eats the click they were making is how a popup gets muted.
    Persistent and loud is enough; blocking is too much.

    Included once from layouts/app.blade.php, next to the toast region. No
    websockets in V1.1 (Reverb is V2); one indexed query every 8s is free.
--}}
<div id="df-desk-popups" aria-live="assertive" aria-label="Front desk alerts"></div>

<style>
    #df-desk-popups {
        position: fixed; top: 64px; left: 50%; transform: translateX(-50%);
        z-index: 9500; width: min(560px, calc(100vw - 24px));
        display: flex; flex-direction: column; gap: 10px; pointer-events: none;
    }
    /* Deliberately RED and deliberately unlike the rest of the OS: this card
       is the one thing on screen that is not part of the page the receptionist
       is working in, and it must not read as another purple panel. */
    .df-desk-card {
        pointer-events: auto;
        background: #fff; border: 2px solid #c62828; border-left: 7px solid #c62828;
        border-radius: 10px; box-shadow: 0 14px 38px rgba(140, 20, 20, .28);
        padding: 12px 14px 10px; font-family: 'DM Sans', system-ui, sans-serif;
        animation: dfDeskIn .18s ease-out;
    }
    @keyframes dfDeskIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { .df-desk-card { animation: none; } }
    .df-desk-kicker { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: #c62828; display: flex; justify-content: space-between; gap: 8px; }
    .df-desk-kicker span:last-child { color: #9ca3af; font-weight: 500; letter-spacing: 0; text-transform: none; }
    .df-desk-title { font-size: 15px; font-weight: 700; color: #1a0a24; margin: 4px 0 2px; line-height: 1.3; }
    .df-desk-msg { font-size: 13px; color: #3d2b47; line-height: 1.45; }
    /* Three facts, label + value, nothing else. A grid so the values line up
       and the eye reads DOWN the column instead of through a sentence. */
    .df-desk-rows { margin: 8px 0 2px; display: grid; grid-template-columns: max-content 1fr; gap: 5px 14px; align-items: baseline; }
    .df-row-l { font-size: 10.5px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #8a8a8a; white-space: nowrap; }
    .df-row-v { font-size: 14px; font-weight: 600; color: #1a0a24; }
    .df-row-v.df-money { font-size: 22px; font-weight: 800; color: #c62828; line-height: 1.15; }
    .df-row-v.df-note  { font-size: 13px; font-weight: 500; color: #3d2b47; }
    .df-desk-actions { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
    .df-desk-actions a, .df-desk-actions button {
        font: 600 12.5px 'DM Sans', system-ui, sans-serif; border-radius: 7px; padding: 7px 14px;
        cursor: pointer; text-decoration: none; border: 1px solid transparent; line-height: 1;
    }
    .df-desk-go    { background: #c62828; color: #fff; }
    .df-desk-go:hover { background: #a81f1f; }
    .df-desk-done  { background: #e4f2ec; color: #0f7355; border-color: #a9d6c4 !important; }
    .df-desk-done:hover { background: #d3ebe0; }
    .df-desk-later { background: #fff; color: #6b7280; border-color: #d1d5db !important; margin-left: auto; }
    .df-desk-later:hover { color: #1a0a24; border-color: #9ca3af !important; }
    @media (max-width: 640px) { #df-desk-popups { top: 56px; } .df-desk-actions { flex-wrap: wrap; } }
    @media print { #df-desk-popups { display: none; } }
</style>

<script>
(function () {
    var POPUPS_URL = '{{ route("notifications.popups") }}';
    var ACK_URL    = '{{ url("/notifications") }}/'; // + id + '/acknowledge' | '/later'
    var CSRF       = '{{ csrf_token() }}';
    // 4s, not 8: the desk noticed the wait. Real-time (Reverb, N-9) removes
    // the poll entirely; until then this is one indexed query twice as often.
    var INTERVAL   = 4000;
    var SEEN_KEY   = 'df_desk_popups_seen';

    var host = document.getElementById('df-desk-popups');
    if (!host) return;

    var shown = {};      // id → card element currently on screen
    var seen  = loadSeen();
    var timer = null;
    var audioCtx = null;

    function loadSeen() {
        try { return JSON.parse(sessionStorage.getItem(SEEN_KEY) || '{}') || {}; } catch (e) { return {}; }
    }
    function markSeen(id) {
        seen[id] = 1;
        try { sessionStorage.setItem(SEEN_KEY, JSON.stringify(seen)); } catch (e) { /* private mode */ }
    }

    // Two-tone chime, synthesised — no asset to serve, no autoplay file.
    // Browsers only allow sound after a user gesture; the first poll after
    // page load may be silent, which is acceptable: the card is still there.
    function chime() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') { audioCtx.resume(); }
            [[880, 0], [1175, 0.16]].forEach(function (t) {
                var o = audioCtx.createOscillator(), g = audioCtx.createGain();
                o.type = 'sine'; o.frequency.value = t[0];
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime + t[1]);
                g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + t[1] + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + t[1] + 0.22);
                o.connect(g); g.connect(audioCtx.destination);
                o.start(audioCtx.currentTime + t[1]); o.stop(audioCtx.currentTime + t[1] + 0.25);
            });
        } catch (e) { /* no audio — the card is enough */ }
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // N-10: three labelled lines — collect, do, book — plus a note if the
    // doctor wrote one. Money is the biggest thing on the card because it is
    // the thing that is forgotten. Older rows carry no payload and fall back
    // to the plain message.
    function row(label, value, mod) {
        if (value === null || value === undefined || value === '') return '';
        return '<span class="df-row-l">' + esc(label) + '</span>' +
               '<span class="df-row-v' + (mod ? ' ' + mod : '') + '">' + esc(value) + '</span>';
    }

    function body(n) {
        var p = n.payload;
        if (!p) {
            return n.message ? '<div class="df-desk-msg">' + esc(n.message) + '</div>' : '';
        }
        var money = p.collect ? '₹' + Number(p.collect).toLocaleString('en-IN') : '';
        var rows  = row('Collect', money, 'df-money') +
                    row('Next action', p.action) +
                    row('Next appointment', p.appointment) +
                    row('Note', p.note, 'df-note');

        return rows ? '<div class="df-desk-rows">' + rows + '</div>' : '';
    }

    function card(n) {
        var el = document.createElement('div');
        el.className = 'df-desk-card';
        el.setAttribute('role', 'alert');
        el.dataset.id = n.id;
        el.innerHTML =
            '<div class="df-desk-kicker"><span>' + esc((n.payload && n.payload.doctor) || 'Front desk') + '</span><span>' + esc(n.time_ago) + '</span></div>' +
            '<div class="df-desk-title">' + esc(n.title) + '</div>' +
            body(n) +
            '<div class="df-desk-actions">' +
                (n.action_url ? '<a class="df-desk-go" href="' + esc(n.action_url) + '">' + esc(n.action_label || 'Open') + ' →</a>' : '') +
                '<button type="button" class="df-desk-done" data-act="acknowledge">Done</button>' +
                '<button type="button" class="df-desk-later" data-act="later">Later</button>' +
            '</div>';
        el.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-act]');
            if (btn) { act(n.id, btn.dataset.act); }
        });
        return el;
    }

    function act(id, action) {
        var el = shown[id];
        if (el) { el.style.opacity = '.5'; el.style.pointerEvents = 'none'; }
        fetch(ACK_URL + id + '/' + action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function () {
            remove(id);
            if (window.DFTopbar && window.DFTopbar.reloadNotifications) { window.DFTopbar.reloadNotifications(); }
        }).catch(function () {
            if (el) { el.style.opacity = ''; el.style.pointerEvents = ''; }
        });
    }

    function remove(id) {
        var el = shown[id];
        if (el && el.parentNode) { el.parentNode.removeChild(el); }
        delete shown[id];
    }

    function reconcile(items) {
        var incoming = {}, fresh = false;
        (items || []).forEach(function (n) {
            incoming[n.id] = true;
            if (!shown[n.id]) {
                var el = card(n);
                host.appendChild(el);
                shown[n.id] = el;
                if (!seen[n.id]) { fresh = true; markSeen(n.id); }
            }
        });
        // Answered elsewhere (a colleague pressed Done, or another tab) → drop it here too.
        Object.keys(shown).forEach(function (id) { if (!incoming[id]) { remove(id); } });
        if (fresh) { chime(); }
    }

    function poll() {
        if (document.visibilityState === 'hidden') return;
        fetch(POPUPS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data && data.items) { reconcile(data.items); } })
            .catch(function () { /* offline or session expired — try again next tick */ });
    }

    function start() {
        if (timer) return;
        poll();
        timer = setInterval(poll, INTERVAL);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { poll(); }
    });

    // Esc = Later on the newest card, so a receptionist typing never has to reach for the mouse.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var ids = Object.keys(shown);
        if (ids.length) { act(ids[ids.length - 1], 'later'); }
    });

    start();
})();
</script>
