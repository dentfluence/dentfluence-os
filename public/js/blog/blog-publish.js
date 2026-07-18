/*
 * Blog Marketing Hub — website publishing panel (Wave 1 Slice 6)
 * ===========================================================================
 * Companion ESM module to blog-editor.js, kept self-contained so publishing is
 * never tangled into the block-canvas/SEO logic. It drives #bp-publish-panel:
 *
 *   - shows the per-target blog_publications ledger (status + external link),
 *   - a "Publish to website" action (create or re-sync via the adapter layer),
 *   - a Retry button when a target failed, and a "Remove from site" action,
 *   - a short poll after an action so an async WordPress job's result surfaces.
 *
 * It only talks to the marketing.blog.publish* endpoints (adapter-independent);
 * WordPress vs standalone is resolved entirely server-side. Nothing here fakes
 * a success — it renders exactly what the ledger reports.
 */
export function initPublishPanel({ getPostUuid, getStatus }) {
    const BOOT = window.__BLOG_EDITOR__ || {};
    const EP = BOOT.endpoints || {};
    const $ = (id) => document.getElementById(id);

    const target = BOOT.publishTarget || 'standalone';
    const hintEl = $('bp-publish-hint');
    const listEl = $('bp-publish-list');
    const btn = $('bp-publish-to-site');
    const noteEl = $('bp-publish-note');

    if (!listEl || !btn) {
        // Panel not on the page (defensive) — return a no-op surface.
        return { refresh() {}, publishToWebsite() {}, refreshButton() {}, render() {} };
    }

    hintEl.textContent = target === 'wordpress'
        ? 'A WordPress site is connected — publishing pushes this post to it.'
        : 'No website connected. Posts are kept in Dentfluence (standalone) until you connect a site in Marketing → Integrations.';

    let pollTimer = null;

    // -----------------------------------------------------------------------
    // Networking
    // -----------------------------------------------------------------------

    function withUuid(tmpl) { return tmpl.replace('__UUID__', getPostUuid()); }
    function withPub(tmpl, pubId) { return tmpl.replace('__UUID__', getPostUuid()).replace('__PUB__', pubId); }

    async function api(url, method) {
        const res = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': BOOT.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, json };
    }

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    // -----------------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------------

    const STATE_LABELS = {
        pending: 'Queued', publishing: 'Publishing…', published: 'Published',
        failed: 'Failed', deleted: 'Removed',
    };

    function render(pubs) {
        listEl.innerHTML = '';
        (pubs || []).forEach((p) => listEl.appendChild(row(p)));
        maybePoll(pubs || []);
        refreshButton();
    }

    function row(p) {
        const wrap = document.createElement('div');
        wrap.className = 'bp-pub-row';
        wrap.dataset.pub = p.id;

        const state = p.status || 'pending';
        const target = (p.target_type || '').replace('_', ' ');

        let meta = '';
        if (p.external_url) {
            meta += `<a href="${esc(p.external_url)}" target="_blank" rel="noopener">View on site ↗</a>`;
        } else if (state === 'published' && p.target_type === 'standalone') {
            meta += 'Stored in Dentfluence (no live site).';
        }
        if (p.last_synced_at) {
            meta += `${meta ? ' · ' : ''}synced ${formatWhen(p.last_synced_at)}`;
        }
        if (p.error) {
            meta += `${meta ? '<br>' : ''}<span class="bp-pub-err">${esc(p.error)}</span>`;
        }

        const actions = [];
        if (state === 'failed') {
            actions.push(`<button type="button" class="bp-btn bp-btn-sm" data-retry="${p.id}">Retry</button>`);
        }
        if (state === 'published' || state === 'failed') {
            actions.push(`<button type="button" class="bp-btn bp-btn-sm" data-remove="${p.id}">Remove from site</button>`);
        }

        wrap.innerHTML = `
            <div class="bp-pub-head">
                <span class="bp-pub-target">${esc(target)}</span>
                <span class="bp-pub-state" data-s="${esc(state)}">${esc(STATE_LABELS[state] || state)}</span>
            </div>
            ${meta ? `<div class="bp-pub-meta">${meta}</div>` : ''}
            ${actions.length ? `<div class="bp-pub-actions">${actions.join('')}</div>` : ''}
        `;
        return wrap;
    }

    function formatWhen(iso) {
        const d = new Date(iso);
        return d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    // Only Published/Scheduled posts push to a website; a draft has nothing to
    // publish yet. Requires the post to exist (saved) first.
    function canPublish() {
        const s = getStatus();
        return !!getPostUuid() && (s === 'published' || s === 'scheduled');
    }

    function refreshButton() {
        btn.disabled = !canPublish();
        btn.textContent = target === 'wordpress' ? 'Publish to WordPress' : 'Record publication';
        noteEl.hidden = canPublish();
    }

    // -----------------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------------

    async function publishToWebsite() {
        if (!getPostUuid()) return;
        btn.disabled = true;
        const prev = btn.textContent;
        btn.textContent = 'Working…';

        const { ok, json } = await api(withUuid(EP.publish), 'POST');
        btn.textContent = prev;

        if (ok) {
            render(json.publications || []);
        } else {
            alert('Could not start publishing. Please try again.');
            refreshButton();
        }
    }

    async function loadPublications() {
        if (!getPostUuid()) { render([]); return; }
        const { ok, json } = await api(withUuid(EP.publications), 'GET');
        if (ok) render(json.publications || []);
    }

    async function retry(pubId, el) {
        el.disabled = true;
        const { ok } = await api(withPub(EP.publicationRetry, pubId), 'POST');
        if (ok) loadPublications();
        else { el.disabled = false; alert('Retry failed. Please try again.'); }
    }

    async function remove(pubId, el) {
        if (!confirm('Remove this post from the website? (WordPress moves it to Trash; it can be restored there.)')) return;
        el.disabled = true;
        const { ok, json } = await api(withPub(EP.publicationDelete, pubId), 'DELETE');
        if (ok && json.success !== false) loadPublications();
        else { el.disabled = false; alert((json && json.error) || 'Could not remove from site.'); }
    }

    // -----------------------------------------------------------------------
    // Polling — a WordPress publish is async (queued job); poll a few times so
    // the pending/publishing → published/failed transition surfaces on its own.
    // -----------------------------------------------------------------------

    let pollsLeft = 0;

    function maybePoll(pubs) {
        const inFlight = pubs.some((p) => p.status === 'pending' || p.status === 'publishing');
        clearTimeout(pollTimer);
        if (!inFlight) { pollsLeft = 0; return; }
        if (pollsLeft <= 0) pollsLeft = 5;
        pollTimer = setTimeout(() => { pollsLeft -= 1; if (pollsLeft >= 0) loadPublications(); }, 3000);
    }

    // -----------------------------------------------------------------------
    // Wiring
    // -----------------------------------------------------------------------

    btn.addEventListener('click', publishToWebsite);

    listEl.addEventListener('click', (e) => {
        const r = e.target.closest('[data-retry]');
        if (r) { retry(r.dataset.retry, r); return; }
        const rm = e.target.closest('[data-remove]');
        if (rm) { remove(rm.dataset.remove, rm); }
    });

    // Boot from the payload the editor already shipped (edit page); the create
    // page ships none until the first save.
    render(BOOT.post?.publications || []);

    return { refresh: loadPublications, publishToWebsite, refreshButton, render };
}
