/*
 * Blog Marketing Hub — inline SEO panel (Wave 1 Slice 4)
 * ===========================================================================
 * Self-contained companion module for blog-editor.js, mounted at
 * #bp-seo-anchor in resources/views/marketing/blog/editor.blade.php. Loaded
 * as a plain ES module via a static `import` from blog-editor.js — no
 * bundler, same esm.sh-free / CDN-free pattern as the rest of the editor
 * (this file has zero external dependencies).
 *
 * Responsibilities:
 *   - Hydrate + wire every blog_post_seo field (focus keyword, secondary
 *     keywords, meta title/description, canonical URL, robots, OG title/
 *     description/image).
 *   - Live character counts for meta title (~50-60) and meta description
 *     (~150-160).
 *   - Two live previews (Google SERP mimic + OG/social card) that recompute
 *     on every relevant keystroke, with the fallback chain: meta_title ||
 *     post title; meta_description || excerpt; og_title || meta_title ||
 *     title; og_description || meta_description || title; og_image ||
 *     featured image.
 *   - The URL-slug field here is a MIRROR of the header #bp-slug field, not a
 *     second source of truth — editing either updates both, and the header
 *     field's native 'input' listener (in blog-editor.js) is what actually
 *     schedules the autosave and carries the slug in the save payload. This
 *     panel's slug field goes read-only + shows a "locked" note whenever the
 *     header field does (BlogPost::isSlugLocked()).
 *
 * Adapter independence: this module reads/writes ONLY blog_post_seo fields
 * (via collect(), folded into the same `seo` payload key BlogPostService has
 * persisted since Slice 2/3). It has no knowledge of WordPress/Standalone/
 * Dentfluence-static publishing — those adapters read this data at publish
 * time (masterplan §4); nothing here depends on or writes to any adapter.
 *
 * Wiring surface (see blog-editor.js's call to initSeoPanel): this module
 * takes exactly four callbacks from the host — markDirty (autosave
 * scheduling), openDam (the shared DAM picker modal, reused verbatim for the
 * OG image), assetById (live DAM lookup) and getFeaturedAssetId (live
 * featured-image id, used as the OG-image fallback). Everything else it
 * reads directly off the DOM (#bp-title, #bp-slug) or off window.__BLOG_EDITOR__,
 * and it re-syncs itself on the 'bp:saved' event blog-editor.js dispatches
 * after every successful save/autosave round-trip.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * MODULAR EXTENSION POINTS (do not build here — future slices mount into
 * these hidden anchors, already present in editor.blade.php):
 *   #bp-seo-ai-anchor             Wave 2 — AI SEO recommendations (Ollama)
 *   #bp-seo-readability-anchor    Wave 2 — Readability score
 *   #bp-seo-score-anchor          Wave 2 — SEO score
 *   #bp-seo-links-anchor          Wave 2 — Internal link suggestions
 *   #bp-seo-schema-anchor         Wave 2/6 — Schema markup
 *   #bp-seo-searchconsole-anchor  Wave 3 — Search Console integration
 *   #bp-seo-ranking-anchor        Wave 4 — Keyword ranking
 * ─────────────────────────────────────────────────────────────────────────
 */

const BOOT = window.__BLOG_EDITOR__ || {};
const $ = (id) => document.getElementById(id);
const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
};
const truncate = (s, n) => (s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s);

export function initSeoPanel(deps) {
    const seed = (BOOT.post && BOOT.post.seo) || {};

    const seo = {
        focusKeyword: seed.focus_keyword || '',
        secondaryKeywords: Array.isArray(seed.secondary_keywords) ? [...seed.secondary_keywords] : [],
        metaTitle: seed.meta_title || '',
        metaDescription: seed.meta_description || '',
        canonicalUrl: seed.canonical_url || '',
        ogTitle: seed.og_title || '',
        ogDescription: seed.og_description || '',
        ogImageAssetId: seed.og_image_asset_id || null,
        noindex: !!seed.noindex,
    };

    const titleEl = $('bp-title');
    const slugEl = $('bp-slug');

    // -----------------------------------------------------------------
    // Focus keyword + secondary keywords (chip list)
    // -----------------------------------------------------------------
    const focusInput = $('bp-seo-focus');
    focusInput.value = seo.focusKeyword;
    focusInput.addEventListener('input', () => { seo.focusKeyword = focusInput.value; deps.markDirty(); });

    function renderKeywordChips() {
        const box = $('bp-seo-keywords');
        box.innerHTML = '';
        seo.secondaryKeywords.forEach((word, i) => {
            const chip = el('span', 'bp-chip', word);
            const x = el('button', null, '×');
            x.type = 'button';
            x.addEventListener('click', () => {
                seo.secondaryKeywords.splice(i, 1);
                renderKeywordChips();
                deps.markDirty();
            });
            chip.append(x);
            box.append(chip);
        });
    }
    function addKeyword() {
        const input = $('bp-seo-keyword-input');
        const word = input.value.trim();
        if (!word) return;
        if (!seo.secondaryKeywords.some((w) => w.toLowerCase() === word.toLowerCase())) {
            seo.secondaryKeywords.push(word);
            renderKeywordChips();
            deps.markDirty();
        }
        input.value = '';
    }
    $('bp-seo-keyword-add').addEventListener('click', addKeyword);
    $('bp-seo-keyword-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); addKeyword(); }
    });
    renderKeywordChips();

    // -----------------------------------------------------------------
    // Meta title / description + live character counts
    // -----------------------------------------------------------------
    const metaTitleEl = $('bp-seo-meta-title');
    const metaDescEl = $('bp-seo-meta-desc');
    metaTitleEl.value = seo.metaTitle;
    metaDescEl.value = seo.metaDescription;

    function updateCount(spanId, len, low, high) {
        const span = $(spanId);
        span.textContent = `${len} / ${high}`;
        span.classList.remove('bp-count-good', 'bp-count-warn', 'bp-count-bad');
        if (len === 0) return;
        if (len < low) span.classList.add('bp-count-warn');
        else if (len <= high) span.classList.add('bp-count-good');
        else span.classList.add('bp-count-bad');
    }

    metaTitleEl.addEventListener('input', () => {
        seo.metaTitle = metaTitleEl.value;
        updateCount('bp-seo-title-count', seo.metaTitle.length, 50, 60);
        refreshPreviews();
        deps.markDirty();
    });
    metaDescEl.addEventListener('input', () => {
        seo.metaDescription = metaDescEl.value;
        updateCount('bp-seo-desc-count', seo.metaDescription.length, 150, 160);
        refreshPreviews();
        deps.markDirty();
    });
    updateCount('bp-seo-title-count', seo.metaTitle.length, 50, 60);
    updateCount('bp-seo-desc-count', seo.metaDescription.length, 150, 160);

    // -----------------------------------------------------------------
    // URL slug — mirrors the header #bp-slug field (single source of truth
    // lives there; this is a second view onto it, not separate state).
    // -----------------------------------------------------------------
    const seoSlugEl = $('bp-seo-slug');
    const seoSlugNote = $('bp-seo-slug-note');

    function syncSlugLockUi() {
        const locked = !!slugEl.disabled;
        seoSlugEl.disabled = locked;
        seoSlugNote.hidden = !locked;
    }
    seoSlugEl.value = slugEl.value;
    syncSlugLockUi();

    seoSlugEl.addEventListener('input', () => {
        if (seoSlugEl.disabled) return;
        slugEl.value = seoSlugEl.value;
        // Re-use blog-editor.js's own #bp-slug listener (marks slugDirty,
        // schedules autosave) instead of duplicating that logic here.
        slugEl.dispatchEvent(new Event('input', { bubbles: true }));
        refreshPreviews();
    });
    slugEl.addEventListener('input', () => {
        seoSlugEl.value = slugEl.value;
        refreshPreviews();
    });
    titleEl.addEventListener('input', () => {
        // blog-editor.js's own title listener (registered before this module
        // initialises) may have just auto-filled #bp-slug from the title
        // without dispatching an 'input' event on it — keep this panel's
        // mirrored slug field visually in sync with that.
        if (!seoSlugEl.disabled) seoSlugEl.value = slugEl.value;
        refreshPreviews();
    });

    // -----------------------------------------------------------------
    // Canonical URL
    // -----------------------------------------------------------------
    const canonicalEl = $('bp-seo-canonical');
    canonicalEl.value = seo.canonicalUrl;
    canonicalEl.addEventListener('input', () => { seo.canonicalUrl = canonicalEl.value.trim(); deps.markDirty(); });

    // -----------------------------------------------------------------
    // Robots — Index / NoIndex
    // -----------------------------------------------------------------
    const indexRadio = $('bp-seo-index');
    const noindexRadio = $('bp-seo-noindex');
    (seo.noindex ? noindexRadio : indexRadio).checked = true;
    [indexRadio, noindexRadio].forEach((r) => r.addEventListener('change', () => {
        seo.noindex = noindexRadio.checked;
        deps.markDirty();
    }));

    // -----------------------------------------------------------------
    // Open Graph title / description
    // -----------------------------------------------------------------
    const ogTitleEl = $('bp-seo-og-title');
    const ogDescEl = $('bp-seo-og-desc');
    ogTitleEl.value = seo.ogTitle;
    ogDescEl.value = seo.ogDescription;
    ogTitleEl.addEventListener('input', () => { seo.ogTitle = ogTitleEl.value; refreshPreviews(); deps.markDirty(); });
    ogDescEl.addEventListener('input', () => { seo.ogDescription = ogDescEl.value; refreshPreviews(); deps.markDirty(); });

    // -----------------------------------------------------------------
    // Open Graph image — reuses the editor's shared DAM picker modal.
    // -----------------------------------------------------------------
    function renderOgImagePicker() {
        const wrap = $('bp-og-image');
        wrap.innerHTML = '';
        const asset = seo.ogImageAssetId ? deps.assetById(seo.ogImageAssetId) : null;
        if (asset) {
            const img = el('img');
            img.src = asset.url;
            wrap.append(img);
        }
        const row = el('div', 'bp-row');
        const choose = el('button', 'bp-btn bp-btn-sm', asset ? 'Replace' : 'Choose image');
        choose.type = 'button';
        choose.addEventListener('click', () => deps.openDam((picked) => {
            seo.ogImageAssetId = picked.id;
            renderOgImagePicker();
            refreshPreviews();
            deps.markDirty();
        }));
        row.append(choose);
        if (asset) {
            const rm = el('button', 'bp-btn bp-btn-sm', 'Remove');
            rm.type = 'button';
            rm.addEventListener('click', () => {
                seo.ogImageAssetId = null;
                renderOgImagePicker();
                refreshPreviews();
                deps.markDirty();
            });
            row.append(rm);
        }
        wrap.append(row);
    }
    renderOgImagePicker();

    // -----------------------------------------------------------------
    // Live previews
    // -----------------------------------------------------------------
    function currentTitle() {
        return (titleEl.value || '').trim() || (BOOT.post && BOOT.post.title) || '';
    }
    function currentExcerpt() {
        return (BOOT.post && BOOT.post.excerpt) || '';
    }

    function refreshSerp() {
        const title = currentTitle();
        const slug = (slugEl.value || '').trim() || 'url-slug';
        const displayTitle = seo.metaTitle.trim() || title || '(Untitled)';
        const displayDesc = seo.metaDescription.trim() || currentExcerpt();
        const host = window.location.hostname || 'yourclinic.com';

        $('bp-serp-url').textContent = `${host} › blog › ${slug}`;
        $('bp-serp-title').textContent = truncate(displayTitle, 65);
        $('bp-serp-desc').textContent = displayDesc ? truncate(displayDesc, 165) : 'Meta description preview appears here as you type…';
    }

    function refreshOg() {
        const title = currentTitle();
        const metaTitleFallback = seo.metaTitle.trim() || title;
        const ogTitle = seo.ogTitle.trim() || metaTitleFallback || '(Untitled)';

        const metaDescFallback = seo.metaDescription.trim() || currentExcerpt();
        // Per spec: og description falls back to meta description, then title.
        const ogDesc = seo.ogDescription.trim() || metaDescFallback || title;

        $('bp-og-card-title').textContent = truncate(ogTitle, 90);
        $('bp-og-card-desc').textContent = ogDesc ? truncate(ogDesc, 200) : 'Description preview appears here…';
        $('bp-og-card-domain').textContent = window.location.hostname || 'yourclinic.com';

        let imgUrl = null;
        if (seo.ogImageAssetId) {
            imgUrl = deps.assetById(seo.ogImageAssetId)?.url || null;
        }
        if (!imgUrl) {
            const featuredId = deps.getFeaturedAssetId();
            if (featuredId) imgUrl = deps.assetById(featuredId)?.url || null;
        }
        const imgBox = $('bp-og-card-img');
        imgBox.innerHTML = '';
        imgBox.classList.toggle('bp-og-card-img-empty', !imgUrl);
        if (imgUrl) {
            const img = el('img');
            img.src = imgUrl;
            imgBox.append(img);
        }
    }

    function refreshPreviews() {
        refreshSerp();
        refreshOg();
    }
    refreshPreviews();

    // Re-sync after every save round-trip: the slug lock may have just
    // engaged (first publish) and BOOT.post.excerpt may have just been
    // auto-filled server-side — both feed this panel's fallbacks.
    document.addEventListener('bp:saved', () => {
        syncSlugLockUi();
        seoSlugEl.value = slugEl.value;
        refreshPreviews();
    });

    // -----------------------------------------------------------------
    // Public surface — blog-editor.js folds this into the `seo` payload key
    // on every autosave/draft/save/publish, exactly like body_json/tag_ids.
    // -----------------------------------------------------------------
    return {
        collect() {
            return {
                focus_keyword: seo.focusKeyword.trim() || null,
                secondary_keywords: seo.secondaryKeywords,
                meta_title: seo.metaTitle.trim() || null,
                meta_description: seo.metaDescription.trim() || null,
                canonical_url: seo.canonicalUrl.trim() || null,
                og_title: seo.ogTitle.trim() || null,
                og_description: seo.ogDescription.trim() || null,
                og_image_asset_id: seo.ogImageAssetId || null,
                noindex: seo.noindex,
            };
        },
    };
}
