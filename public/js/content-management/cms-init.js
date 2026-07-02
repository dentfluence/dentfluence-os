/**
 * cms-init.js
 * Bootstraps the Content Management module.
 * Loaded on every CMS page.
 */
(function () {
    'use strict';

    window.CMS = window.CMS || {};

    // ── CSRF token helper ─────────────────────────────────────
    CMS.csrfToken = function () {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };

    // ── Loading state ─────────────────────────────────────────
    CMS.showLoading = function () {
        var el = document.getElementById('cms-loading');
        if (el) el.style.display = 'flex';
    };

    CMS.hideLoading = function () {
        var el = document.getElementById('cms-loading');
        if (el) el.style.display = 'none';
    };

    // ── Toast shortcut ────────────────────────────────────────
    CMS.toast = function (msg, type) {
        if (window.DFLayout?.toast) {
            DFLayout.toast(msg, type || 'info');
        }
    };

    // ── Media preview lightbox ────────────────────────────────
    window.previewMedia = function (url, type, filename) {
        if (!url) return;

        // Remove any existing lightbox
        var existing = document.getElementById('cms-lightbox');
        if (existing) existing.remove();

        var lb = document.createElement('div');
        lb.id = 'cms-lightbox';
        lb.style.cssText = 'position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
        lb.onclick = function () { lb.remove(); };

        var inner = '';
        if (type === 'video') {
            inner = '<video src="' + url + '" controls style="max-width:90vw;max-height:90vh;border-radius:6px;" onclick="event.stopPropagation()"></video>';
        } else if (type === 'pdf') {
            inner = '<iframe src="' + url + '" style="width:90vw;height:90vh;border-radius:6px;border:none;" onclick="event.stopPropagation()"></iframe>';
        } else {
            inner = '<img src="' + url + '" style="max-width:90vw;max-height:90vh;border-radius:6px;object-fit:contain;" onclick="event.stopPropagation()" alt="' + (filename || '') + '">';
        }

        // Close button
        inner += '<button onclick="document.getElementById(\'cms-lightbox\').remove()" style="position:fixed;top:16px;right:20px;background:rgba(255,255,255,.15);border:none;color:white;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;">×</button>';

        lb.innerHTML = inner;
        document.body.appendChild(lb);
    };

    // ── Tag as marketing ──────────────────────────────────────
    window.tagAsMarketing = function (mediaId, btn) {
        var isMarketing = btn.dataset.marketing === '1';
        var url = isMarketing
            ? '/content-management/tag-marketing/' + mediaId
            : '/content-management/tag-marketing';

        fetch(url, {
            method: isMarketing ? 'DELETE' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CMS.csrfToken(),
                'Accept': 'application/json',
            },
            body: isMarketing ? null : JSON.stringify({ media_id: mediaId }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    CMS.toast(data.message || 'Done', 'success');
                    // Toggle button state
                    if (isMarketing) {
                        btn.dataset.marketing = '0';
                        btn.style.borderColor = '#e5e7eb';
                        btn.style.color = '#374151';
                        btn.style.background = 'white';
                        btn.innerHTML = btn.innerHTML.replace('Remove Marketing Tag', 'Tag as Marketing');
                    } else {
                        btn.dataset.marketing = '1';
                        btn.style.borderColor = '#dc2626';
                        btn.style.color = '#dc2626';
                        btn.style.background = '#fef2f2';
                        btn.innerHTML = btn.innerHTML.replace('Tag as Marketing', 'Remove Marketing Tag');
                    }
                }
            })
            .catch(function () { CMS.toast('Something went wrong', 'error'); });
    };

    // ── Row context menu ──────────────────────────────────────
    window.cmsRowMenu = function (event, id) {
        event.stopPropagation();
        // Simple context menu — open case viewer with more options
        openCaseViewer(id);
    };

    // ── Gallery filter (full gallery tab) ────────────────────
    window.filterGallery = function (type) {
        document.querySelectorAll('.gal-item').forEach(function (el) {
            if (type === 'all' || el.dataset.type === type) {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        });
        // Update active button
        document.querySelectorAll('[id^="gal-btn-"]').forEach(function (btn) {
            btn.style.background = 'white';
            btn.style.color = '#6b7280';
            btn.style.borderColor = '#e5e7eb';
        });
        var active = document.getElementById('gal-btn-' + type);
        if (active) {
            active.style.background = '#6a0f70';
            active.style.color = 'white';
            active.style.borderColor = '#6a0f70';
        }
    };

    console.log('[CMS] Initialized');
    // ── Watermark Settings ──
    window.previewWmLogo = function (input) {
        if (!input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var src = e.target.result;
            document.getElementById('wm-logo-preview').innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:contain;">';
            document.getElementById('wm-prev-logo').innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:contain;">';
        };
        reader.readAsDataURL(input.files[0]);
    };

    window.setWmPosition = function (val, btn) {
        document.getElementById('wm_position').value = val;
        document.querySelectorAll('[data-position]').forEach(function (b) {
            b.style.background = 'white';
            b.style.color = '#6b7280';
            b.style.borderColor = '#e5e7eb';
        });
        btn.style.background = '#6a0f70';
        btn.style.color = 'white';
        btn.style.borderColor = '#6a0f70';
    };

    document.addEventListener('DOMContentLoaded', function () {
        ['wm_clinic_name', 'wm_doctor_name'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function () {
                if (id === 'wm_clinic_name') document.getElementById('wm-prev-clinic').textContent = el.value || '';
                if (id === 'wm_doctor_name') document.getElementById('wm-prev-doctor').textContent = el.value || 'Doctor Name';
            });
        });
    });

    window.saveWmSettings = async function () {
        var form = document.getElementById('wm-settings-form');
        var fd = new FormData(form);
        try {
            var r = await fetch('/content-management/watermark-settings', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CMS.csrfToken() },
                body: fd,
            });
            var d = await r.json();
            if (d.success) {
                document.getElementById('wm-settings-modal').style.display = 'none';
                CMS.toast('Watermark settings saved.', 'success');
            }
        } catch (e) {
            CMS.toast('Error saving settings.', 'error');
        }
    };

})();
