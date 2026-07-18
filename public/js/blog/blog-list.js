/*
 * Blog Marketing Hub — CMS list (Wave 1 Slice 5)
 * ===========================================================================
 * Plain script (no bundler/ESM needed — nothing here is imported anywhere
 * else), following the same fetch/CSRF convention as blog-editor.js. Handles
 * the row actions that need JS: duplicate (redirects to the new post's
 * editor), archive/unarchive/delete (POST/DELETE then reload so the
 * server-rendered list/counts stay the single source of truth), and the
 * version-history modal (list/restore/delete-version).
 *
 * Everything else on this page — status filter tabs, search, pagination — is
 * plain server-rendered links/forms and needs no JS at all.
 */
(function () {
    'use strict';

    const BOOT = window.__BLOG_LIST__ || {};
    const $ = (id) => document.getElementById(id);

    function withUuid(tmpl, uuid) {
        return tmpl.replace('__UUID__', uuid);
    }

    function withVersion(tmpl, uuid, versionId) {
        return tmpl.replace('__UUID__', uuid).replace('__VERSION__', versionId);
    }

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

    // -----------------------------------------------------------------------
    // Row actions (event delegation — rows are server-rendered, no re-render)
    // -----------------------------------------------------------------------

    async function handleRowAction(btn) {
        const action = btn.dataset.action;
        const uuid = btn.dataset.uuid;
        if (!action || !uuid) return;

        if (action === 'history') {
            openHistory(uuid, btn.dataset.title || '');
            return;
        }

        if (action === 'duplicate') {
            btn.disabled = true;
            const { ok, json } = await api(withUuid(BOOT.endpoints.duplicate, uuid), 'POST');
            btn.disabled = false;
            if (ok && json.post && json.post.uuid) {
                window.location.href = withUuid(BOOT.endpoints.editPage, json.post.uuid);
            } else {
                alert('Could not duplicate this post. Please try again.');
            }
            return;
        }

        if (action === 'archive' || action === 'unarchive') {
            btn.disabled = true;
            const { ok } = await api(withUuid(BOOT.endpoints[action], uuid), 'POST');
            if (ok) {
                window.location.reload();
            } else {
                btn.disabled = false;
                alert('That action failed. Please try again.');
            }
            return;
        }

        if (action === 'delete') {
            const title = btn.dataset.title || 'this post';
            if (!confirm(`Delete "${title}"? This can be restored by support but disappears from every list immediately.`)) {
                return;
            }
            btn.disabled = true;
            const { ok } = await api(withUuid(BOOT.endpoints.destroy, uuid), 'DELETE');
            if (ok) {
                window.location.reload();
            } else {
                btn.disabled = false;
                alert('Could not delete this post. Please try again.');
            }
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (btn && document.getElementById('blog-list-root')?.contains(btn)) {
            handleRowAction(btn);
        }
    });

    // -----------------------------------------------------------------------
    // Row actions kebab (⋮) menu — this is only the trigger UI. Each menu item
    // still carries the same data-action/data-uuid the delegated handler above
    // reacts to, so endpoints/behaviour are unchanged. The menu is positioned
    // fixed (measured off its button) so the list container's overflow:hidden
    // never clips it. Closes on outside click, Escape, scroll and resize.
    // -----------------------------------------------------------------------
    let openMenu = null;

    function closeMenu() {
        if (!openMenu) return;
        openMenu.hidden = true;
        if (openMenu._btn) openMenu._btn.setAttribute('aria-expanded', 'false');
        openMenu.style.position = '';
        openMenu.style.top = '';
        openMenu.style.left = '';
        openMenu._btn = null;
        openMenu = null;
    }

    function toggleMenu(btn) {
        const menu = btn.parentElement.querySelector('.bl-kebab-menu');
        if (!menu) return;
        const wasOpen = openMenu === menu;
        closeMenu();
        if (wasOpen) return;

        menu.hidden = false; // show first so we can measure its width
        const r = btn.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        menu._btn = btn;
        btn.setAttribute('aria-expanded', 'true');
        openMenu = menu;

        const first = menu.querySelector('.bl-menu-item');
        if (first) first.focus();
    }

    document.addEventListener('click', (e) => {
        const kebab = e.target.closest('.bl-kebab-btn');
        if (kebab && document.getElementById('blog-list-root')?.contains(kebab)) {
            e.stopPropagation();
            toggleMenu(kebab);
            return;
        }
        // A click on a menu item runs its data-action handler (above); either
        // way, any click that isn't the toggle button closes the open menu.
        closeMenu();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && openMenu) {
            const btn = openMenu._btn;
            closeMenu();
            if (btn) btn.focus();
        }
    });

    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);

    // -----------------------------------------------------------------------
    // Version history modal
    // -----------------------------------------------------------------------

    const historyState = { uuid: null };

    function labelText(label) {
        return { autosave: 'Autosave', manual: 'Manual', publish: 'Published', scheduled: 'Scheduled' }[label] || label;
    }

    function formatWhen(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    async function openHistory(uuid, title) {
        historyState.uuid = uuid;
        $('bl-history-title').textContent = title;
        $('bl-history-list').innerHTML = '<div class="bl-history-empty">Loading…</div>';
        $('bl-history-modal').hidden = false;
        await loadVersions();
    }

    function closeHistory() {
        $('bl-history-modal').hidden = true;
        historyState.uuid = null;
    }

    async function loadVersions() {
        const uuid = historyState.uuid;
        if (!uuid) return;

        const { ok, json } = await api(withUuid(BOOT.endpoints.versionsIndex, uuid), 'GET');
        const list = $('bl-history-list');

        if (!ok) {
            list.innerHTML = '<div class="bl-history-empty">Could not load history.</div>';
            return;
        }

        const versions = json.versions || [];
        if (versions.length === 0) {
            list.innerHTML = '<div class="bl-history-empty">No history yet.</div>';
            return;
        }

        list.innerHTML = '';
        versions.forEach((v) => {
            const row = document.createElement('div');
            row.className = 'bl-history-row';
            row.innerHTML = `
                <span class="bl-history-label" data-label="${v.label}">${labelText(v.label)}</span>
                <span class="bl-history-meta">
                    ${formatWhen(v.created_at)}
                    <small>${v.editor && v.editor.name ? 'by ' + v.editor.name : 'system'}</small>
                </span>
                <span class="bl-history-actions">
                    <button type="button" class="bl-history-btn" data-restore="${v.id}">Restore</button>
                    <button type="button" class="bl-history-btn" data-delete-version="${v.id}" style="color:#c62828; background:#fdecec;">Delete</button>
                </span>
            `;
            list.appendChild(row);
        });
    }

    $('bl-history-close')?.addEventListener('click', closeHistory);
    $('bl-history-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'bl-history-modal') closeHistory();
    });

    $('bl-history-list')?.addEventListener('click', async (e) => {
        const uuid = historyState.uuid;
        if (!uuid) return;

        const restoreBtn = e.target.closest('[data-restore]');
        if (restoreBtn) {
            const versionId = restoreBtn.dataset.restore;
            restoreBtn.disabled = true;
            const { ok } = await api(withVersion(BOOT.endpoints.versionsRestore, uuid, versionId), 'POST');
            if (ok) {
                // Title/status/etc. may have changed — reload so the list row
                // reflects the restored content, same as archive/delete above.
                window.location.reload();
            } else {
                restoreBtn.disabled = false;
                alert('Could not restore this version. Please try again.');
            }
            return;
        }

        const deleteBtn = e.target.closest('[data-delete-version]');
        if (deleteBtn) {
            if (!confirm('Delete this version permanently? This cannot be undone.')) return;
            const versionId = deleteBtn.dataset.deleteVersion;
            deleteBtn.disabled = true;
            const { ok } = await api(withVersion(BOOT.endpoints.versionsDestroy, uuid, versionId), 'DELETE');
            if (ok) {
                await loadVersions();
            } else {
                deleteBtn.disabled = false;
                alert('Could not delete this version. Please try again.');
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !$('bl-history-modal').hidden) closeHistory();
    });
})();
