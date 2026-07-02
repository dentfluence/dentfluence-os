/**
 * cms-search.js
 * Handles all search, filter, sort and pagination for the Clinical tab.
 */
(function () {
    'use strict';

    var debounceTimer = null;

    window.cmsSearch = {

        // ── Collect current filter values ─────────────────────
        getFilters: function () {
            return {
                q:          document.getElementById('cms-q')?.value.trim()         || '',
                patient_id: document.getElementById('cms-patient')?.value          || '',
                tooth_no:   document.getElementById('cms-tooth')?.value.trim()     || '',
                treatment:  document.getElementById('cms-treatment')?.value        || '',
                status:     document.getElementById('cms-status')?.value           || '',
                date_from:  document.getElementById('cms-date-from')?.value        || '',
                date_to:    document.getElementById('cms-date-to')?.value          || '',
                sort:       document.getElementById('cms-sort')?.value             || 'upload_date_desc',
                per_page:   document.getElementById('cms-per-page')?.value         || '10',
                page:       this._currentPage || 1,
            };
        },

        _currentPage: 1,

        // ── Debounced search (fires 400ms after last keystroke) ──
        debounceSearch: function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                window.cmsSearch.doSearch();
            }, 400);
        },

        // ── Main search ───────────────────────────────────────
        doSearch: function (page) {
            this._currentPage = page || 1;
            var filters = this.getFilters();

            CMS.showLoading();
            this.updateActivePills(filters);

            var params = new URLSearchParams(filters).toString();

            fetch('/content-management/search?' + params, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                    'X-CSRF-TOKEN':     CMS.csrfToken(),
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var wrap = document.getElementById('cms-results-wrap');
                if (wrap) wrap.innerHTML = data.html || '';

                var count = document.getElementById('cms-results-count');
                if (count) count.textContent = 'Showing ' + data.total + ' result(s)';

                CMS.hideLoading();
            })
            .catch(function (err) {
                CMS.hideLoading();
                CMS.toast('Search failed. Please retry.', 'error');
                console.error('[cmsSearch]', err);
            });
        },

        // ── Sort shortcut ─────────────────────────────────────
        sort: function (value) {
            this.doSearch();
        },

        // ── Pagination ────────────────────────────────────────
        goPage: function (page) {
            this.doSearch(page);
            // Scroll back to top of results
            var wrap = document.getElementById('cms-results-wrap');
            if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        // ── Reset all filters ────────────────────────────────
        reset: function () {
            ['cms-q','cms-tooth','cms-patient','cms-treatment',
             'cms-status','cms-date-from','cms-date-to'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('cms-active-filters').innerHTML = '';
            this.doSearch();
        },

        // ── Pre-filter by patient (called from patient profile) ─
        setPatient: function (id, name) {
            var sel = document.getElementById('cms-patient');
            if (sel) sel.value = id;
            this.doSearch();
        },

        // ── Active filter pills ──────────────────────────────
        updateActivePills: function (filters) {
            var container = document.getElementById('cms-active-filters');
            if (!container) return;

            var pills = [];

            var labels = {
                tooth_no:  'Tooth No.: ',
                treatment: 'Treatment: ',
                status:    'Status: ',
                date_from: 'From: ',
                date_to:   'To: ',
                q:         'Search: ',
            };

            Object.entries(labels).forEach(function (entry) {
                var key = entry[0], label = entry[1];
                if (filters[key]) {
                    pills.push('<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:#f5f3ff;border:1px solid #e9d5ff;border-radius:99px;font-size:11px;font-weight:600;color:#6a0f70;">'
                        + label + filters[key]
                        + '<button onclick="cmsSearch.clearFilter(\'' + key + '\')" style="background:none;border:none;cursor:pointer;color:#b95cb7;font-size:13px;line-height:1;padding:0;">×</button>'
                        + '</span>');
                }
            });

            if (filters.patient_id) {
                var sel = document.getElementById('cms-patient');
                var name = sel ? sel.options[sel.selectedIndex]?.text : 'Patient';
                pills.push('<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:#f5f3ff;border:1px solid #e9d5ff;border-radius:99px;font-size:11px;font-weight:600;color:#6a0f70;">'
                    + 'Patient: ' + name
                    + '<button onclick="cmsSearch.clearFilter(\'patient_id\')" style="background:none;border:none;cursor:pointer;color:#b95cb7;font-size:13px;line-height:1;padding:0;">×</button>'
                    + '</span>');
            }

            if (pills.length > 0) {
                pills.push('<button onclick="cmsSearch.reset()" style="padding:3px 10px;border:1px solid #fecaca;border-radius:99px;font-size:11px;font-weight:600;color:#dc2626;background:white;cursor:pointer;">Clear All</button>');
            }

            container.innerHTML = pills.join('');
        },

        // ── Clear a single filter ────────────────────────────
        clearFilter: function (key) {
            var el = document.getElementById('cms-' + key.replace('_', '-'));
            if (el) el.value = '';
            this.doSearch();
        },

    };

    // Auto-search on page load if there are pre-set filters
    document.addEventListener('DOMContentLoaded', function () {
        var params = new URLSearchParams(window.location.search);
        if (params.get('tab') === 'clinical' || !params.get('tab')) {
            // Small delay so Alpine.js renders first
            setTimeout(function () {
                window.cmsSearch.doSearch();
            }, 100);
        }
    });

})();
