/*
 * Blog Marketing Hub — single-surface rich-text editor
 * ===========================================================================
 * Wix / Google-Docs-style writing experience: a title + slug, a persistent
 * word-processor toolbar, and ONE flowing TipTap document (no per-block
 * cards, no "+ Add block"). The whole post body is stored as a SINGLE
 * canonical block:
 *
 *   body_json = { version:1, blocks:[ { id, type:'richtext', data:{ html } } ] }
 *
 * The backend is unchanged in shape: BlogBlockRenderer turns that richtext
 * block into sanitised, portable HTML (block-level allowlist) exactly the way
 * it already renders every other block type, so the WordPress/publish path is
 * unaffected. Legacy multi-block drafts still open here — they are converted
 * to one HTML document client-side on load (see legacyBlocksToHtml) and
 * re-saved as a richtext block.
 *
 * Loading path: pinned ESM CDN (esm.sh), same pattern the whole app uses.
 * Every @tiptap/* package is pinned to one version and shares a single
 * ProseMirror via `?deps=@tiptap/pm@…` so no duplicate-plugin errors occur.
 *
 * The SEO panel (./blog-seo.js) and website-publishing panel (./blog-publish.js)
 * bind by element id and are wired in unchanged — this rewrite preserves every
 * id they depend on.
 */

import { initSeoPanel } from './blog-seo.js';
import { initPublishPanel } from './blog-publish.js';

const TIPTAP_VERSION = '2.11.5';
const DEPS = '?deps=@tiptap/pm@' + TIPTAP_VERSION;
const cdn = (pkg) => `https://esm.sh/@tiptap/${pkg}@${TIPTAP_VERSION}${DEPS}`;

// Full word-processor extension set. All pinned to one version + a shared
// ProseMirror. Color depends on TextStyle; Highlight renders <mark>.
const [
    { Editor },
    Document,
    Paragraph,
    Text,
    Heading,
    Bold,
    Italic,
    Underline,
    Strike,
    Code,
    Blockquote,
    BulletList,
    OrderedList,
    ListItem,
    Link,
    Image,
    TextAlign,
    TextStyle,
    Color,
    Highlight,
    History,
    HardBreak,
] = await Promise.all([
    import(cdn('core')),
    import(cdn('extension-document')).then((m) => m.default),
    import(cdn('extension-paragraph')).then((m) => m.default),
    import(cdn('extension-text')).then((m) => m.default),
    import(cdn('extension-heading')).then((m) => m.default),
    import(cdn('extension-bold')).then((m) => m.default),
    import(cdn('extension-italic')).then((m) => m.default),
    import(cdn('extension-underline')).then((m) => m.default),
    import(cdn('extension-strike')).then((m) => m.default),
    import(cdn('extension-code')).then((m) => m.default),
    import(cdn('extension-blockquote')).then((m) => m.default),
    import(cdn('extension-bullet-list')).then((m) => m.default),
    import(cdn('extension-ordered-list')).then((m) => m.default),
    import(cdn('extension-list-item')).then((m) => m.default),
    import(cdn('extension-link')).then((m) => m.default),
    import(cdn('extension-image')).then((m) => m.default),
    import(cdn('extension-text-align')).then((m) => m.default),
    import(cdn('extension-text-style')).then((m) => m.default),
    import(cdn('extension-color')).then((m) => m.default),
    import(cdn('extension-highlight')).then((m) => m.default),
    import(cdn('extension-history')).then((m) => m.default),
    import(cdn('extension-hard-break')).then((m) => m.default),
]);

// ---------------------------------------------------------------------------
// Boot data + small helpers
// ---------------------------------------------------------------------------

const BOOT = window.__BLOG_EDITOR__ || {};
const $ = (id) => document.getElementById(id);
const el = (tag, cls, html) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
};
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));
const uid = () => 'blk_' + Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-3);

// ---------------------------------------------------------------------------
// Editor state
// ---------------------------------------------------------------------------

const state = {
    postUuid: BOOT.post?.uuid || null,
    slugLocked: !!BOOT.post?.slug_locked,
    slugDirty: false,
    // Stable id for the single richtext block (kept across saves; reused from an
    // existing richtext doc so the block id is stable across the post's life).
    bodyBlockId: uid(),
    tagIds: [...(BOOT.post?.tag_ids || [])],
    categoryId: BOOT.post?.category_id || null,
    featuredAssetId: BOOT.post?.featured_asset_id || null,
    assets: [...(BOOT.assets || [])],
    tags: [...(BOOT.tags || [])],
    saving: false,
    autosaveTimer: null,
};

let editor = null; // the single TipTap Editor (created in mountEditor)

// ---------------------------------------------------------------------------
// Selection preservation for "focus-stealing" toolbar controls
// ---------------------------------------------------------------------------
// Plain toolbar BUTTONS keep the editor's selection intact by preventDefault-
// ing mousedown, so a click never blurs the ProseMirror view in the first
// place. Controls that must themselves take native focus — the block-type
// <select>, the native <input type=color>, window.prompt() for links, and
// the DAM picker modal for images — can't use that trick: interacting with
// them blurs the editor for real. TipTap's `.focus()` is documented to
// restore "the last selection", but that was confirmed unreliable here
// (selecting text then choosing "Heading 2" left it a plain paragraph), so
// instead we track the selection ourselves on every selectionUpdate/blur
// while the editor has it, and explicitly re-apply it with setTextSelection
// before running any command triggered from outside the editor.
let savedSelection = null;

function captureSelection() {
    if (!editor) return;
    const { from, to } = editor.state.selection;
    savedSelection = { from, to };
}

// Runs build(chain) against a chain that is focused AND has the
// previously-captured selection explicitly re-applied, then executes it.
function withSelection(build) {
    if (!editor) return;
    let chain = editor.chain().focus();
    if (savedSelection) {
        const max = editor.state.doc.content.size;
        const from = Math.min(savedSelection.from, max);
        const to = Math.min(savedSelection.to, max);
        chain = chain.setTextSelection({ from, to });
    }
    build(chain).run();
}

const assetById = (id) => state.assets.find((a) => Number(a.id) === Number(id));

// Set during init to the objects returned by blog-seo.js / blog-publish.js.
let SeoPanel = null;
let PublishPanel = null;

// ---------------------------------------------------------------------------
// Networking
// ---------------------------------------------------------------------------

function withUuid(tmpl) { return tmpl.replace('__UUID__', state.postUuid); }

async function api(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': BOOT.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const json = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, json };
}

// ---------------------------------------------------------------------------
// Serialisation (editor -> block-JSON)
// ---------------------------------------------------------------------------
// The document is ONE richtext block: the server (BlogBlockRenderer) sanitises
// the html to a block-level allowlist and outputs it verbatim, so the stored
// contract and the publish path stay identical for every render target.

function serialize() {
    const html = editor ? editor.getHTML() : '';
    return {
        version: 1,
        blocks: [{ id: state.bodyBlockId, type: 'richtext', data: { html } }],
    };
}

// ---------------------------------------------------------------------------
// Legacy conversion: old multi-block drafts -> one HTML document
// ---------------------------------------------------------------------------
// Mirrors BlogBlockRenderer's output closely so an older draft opens seamlessly
// in the single surface. It will re-save as a richtext block on the next save.
// Notes:
//   - divider: dropped (the richtext allowlist / editor schema has no <hr>).
//   - table: flattened to paragraphs (no Table extension / not in the
//     allowlist) so the text survives rather than being stripped.

function legacyBlocksToHtml(blocks) {
    return blocks.map(legacyBlockToHtml).filter(Boolean).join('\n');
}

function legacyBlockToHtml(b) {
    const d = b.data || {};
    switch (b.type) {
        case 'richtext':
            return typeof d.html === 'string' ? d.html : '';

        case 'heading': {
            const lvl = Math.min(4, Math.max(2, Number(d.level) || 2)); // editor supports h2–h4
            const t = String(d.text || '').trim();
            return t ? `<h${lvl}>${esc(t)}</h${lvl}>` : '';
        }

        case 'paragraph': {
            if (d.html) return `<p>${d.html}</p>`; // inner inline html (already limited)
            const t = String(d.text || '').trim();
            return t ? `<p>${esc(t).replace(/\r?\n/g, '<br>')}</p>` : '';
        }

        case 'quote': {
            const t = String(d.text || '').trim();
            if (!t) return '';
            const cite = String(d.cite || '').trim();
            const body = esc(t).replace(/\r?\n/g, '<br>');
            return `<blockquote><p>${body}</p>${cite ? `<p>— ${esc(cite)}</p>` : ''}</blockquote>`;
        }

        case 'list': {
            const items = Array.isArray(d.items) ? d.items : [];
            const lis = items
                .filter((x) => typeof x === 'string' && x.trim() !== '')
                .map((x) => `<li>${x}</li>`) // item html carries the same allowlisted marks
                .join('');
            if (!lis) return '';
            return d.style === 'number' ? `<ol>${lis}</ol>` : `<ul>${lis}</ul>`;
        }

        case 'image': {
            const a = d.asset_id ? assetById(d.asset_id) : null;
            const src = a?.url || d.url || '';
            if (!src) return '';
            const img = `<img src="${esc(src)}" alt="${esc(d.alt || '')}">`;
            const cap = String(d.caption || '').trim();
            return cap ? `<figure>${img}<figcaption>${esc(cap)}</figcaption></figure>` : `<figure>${img}</figure>`;
        }

        case 'cta': {
            const label = String(d.label || '').trim();
            const url = String(d.url || '').trim();
            return (label && url) ? `<p><a href="${esc(url)}">${esc(label)}</a></p>` : '';
        }

        case 'faq': {
            const items = Array.isArray(d.items) ? d.items : [];
            return items.map((it) => {
                const q = String(it?.q || '').trim();
                const a = String(it?.a || '').trim();
                if (!q || !a) return '';
                return `<p><strong>${esc(q)}</strong></p><p>${esc(a).replace(/\r?\n/g, '<br>')}</p>`;
            }).filter(Boolean).join('');
        }

        case 'table': {
            const rows = Array.isArray(d.rows) ? d.rows : [];
            return rows
                .map((r) => (Array.isArray(r) ? r : []).map((c) => esc(String(c ?? ''))).join(' — '))
                .filter((line) => line.trim() !== '')
                .map((line) => `<p>${line}</p>`)
                .join('');
        }

        case 'divider':
            return ''; // no <hr> in the richtext allowlist / editor schema

        default:
            return '';
    }
}

// Resolve the initial document HTML from the boot payload.
function computeInitialHtml() {
    const post = BOOT.post;
    if (!post) return '';
    const blocks = post.body_json?.blocks || [];
    if (blocks.length === 1 && blocks[0]?.type === 'richtext') {
        if (blocks[0].id) state.bodyBlockId = blocks[0].id; // keep the stable id
        return typeof blocks[0].data?.html === 'string' ? blocks[0].data.html : '';
    }
    if (blocks.length) return legacyBlocksToHtml(blocks); // legacy typed blocks
    return '';
}

// ---------------------------------------------------------------------------
// Editor + toolbar
// ---------------------------------------------------------------------------

function mountEditor(html) {
    editor = new Editor({
        element: $('bp-editor'),
        extensions: [
            Document,
            Paragraph,
            Text,
            Heading.configure({ levels: [2, 3, 4] }),
            Bold,
            Italic,
            Underline,
            Strike,
            Code,
            Blockquote,
            BulletList,
            OrderedList,
            ListItem,
            Link.configure({ openOnClick: false, autolink: false }),
            Image.configure({ inline: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            TextStyle,
            Color,
            Highlight,
            History,
            HardBreak,
        ],
        content: html || '',
        onUpdate: () => markDirty(),
        onSelectionUpdate: () => { captureSelection(); updateToolbar(); },
        onTransaction: () => updateToolbar(),
        onBlur: () => captureSelection(),
    });

    // Defensive: a global app shortcut hijacks "/" (confirmed). Stop the event
    // bubbling to document-level handlers while the caret is inside the document
    // so typing a slash never triggers it. (Insertion is toolbar-only anyway.)
    editor.view.dom.addEventListener('keydown', (e) => {
        if (e.key === '/') e.stopPropagation();
    });

    wireToolbarControls();
    updateToolbar();
}

function promptLink() {
    const prev = editor.getAttributes('link').href || '';
    // window.prompt() is a native modal — it blurs the editor for the
    // duration, same class of problem as the select/color controls, so the
    // eventual command goes through withSelection() too.
    const url = window.prompt('Link URL', prev);
    if (url === null) return; // cancelled
    if (url.trim() === '') { withSelection((chain) => chain.extendMarkRange('link').unsetLink()); return; }
    withSelection((chain) => chain.extendMarkRange('link').setLink({ href: url.trim() }));
}

// Toolbar image button -> EXISTING DAM picker -> insert <img src alt> at the
// cursor. No "/" involved (see the keydown guard above). The DAM modal opens
// and stays open across several clicks before the user picks an asset, so by
// the time this callback runs the editor has long since lost focus — restore
// the selection captured before the modal opened.
function insertImageFromDam() {
    openDam((asset) => {
        const src = asset.url || '';
        if (!src) return;
        const alt = asset.alt || asset.name || '';
        withSelection((chain) => chain.setImage({ src, alt }));
        markDirty();
    });
}

function wireToolbarControls() {
    const bar = $('bp-toolbar');

    // Chain-command buttons — all run through withSelection() so they apply
    // to the selection that was live when the toolbar was interacted with,
    // not whatever the (possibly-collapsed) selection happens to be by the
    // time the command actually executes.
    const chainCmds = {
        bold: (chain) => chain.toggleBold(),
        italic: (chain) => chain.toggleItalic(),
        underline: (chain) => chain.toggleUnderline(),
        strike: (chain) => chain.toggleStrike(),
        code: (chain) => chain.toggleCode(),
        blockquote: (chain) => chain.toggleBlockquote(),
        bulletList: (chain) => chain.toggleBulletList(),
        orderedList: (chain) => chain.toggleOrderedList(),
        highlight: (chain) => chain.toggleHighlight(),
        'align-left': (chain) => chain.setTextAlign('left'),
        'align-center': (chain) => chain.setTextAlign('center'),
        'align-right': (chain) => chain.setTextAlign('right'),
    };
    // Standalone controls that need more than a single chained command
    // (native prompt / DAM modal) but still apply via withSelection internally.
    const standaloneCmds = { link: promptLink, image: insertImageFromDam };

    bar.querySelectorAll('[data-cmd]').forEach((btn) => {
        // preventDefault on mousedown keeps the document's selection intact —
        // otherwise the click would blur the editor and collapse the range
        // before the command runs. (withSelection() below is a second,
        // belt-and-braces layer in case focus is lost anyway.)
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const name = btn.dataset.cmd;
            if (chainCmds[name]) {
                withSelection(chainCmds[name]);
                updateToolbar();
                return;
            }
            const fn = standaloneCmds[name];
            if (fn) { fn(); updateToolbar(); }
        });
    });

    // Block-type dropdown (Paragraph / Heading 2 / 3 / 4). Choosing an option
    // in a native <select> blurs the editor before "change" fires, so the
    // command is applied via withSelection() (focus + explicit
    // setTextSelection of the range captured before the select took focus),
    // not a bare chain().focus() (confirmed unreliable on its own).
    const blockSel = $('bp-tb-block');
    blockSel.addEventListener('change', () => {
        const v = blockSel.value;
        withSelection((chain) => (
            v === 'paragraph' ? chain.setParagraph() : chain.setHeading({ level: Number(v.slice(1)) })
        ));
        updateToolbar();
    });

    // Text colour. The native colour input steals focus for real; same fix
    // as the block-type select — restore the captured selection explicitly
    // rather than relying on chain().focus() alone.
    const color = $('bp-tb-color');
    color.addEventListener('input', () => {
        withSelection((chain) => chain.setColor(color.value));
        $('bp-tb-color-bar').style.background = color.value;
        updateToolbar();
    });
}

function updateToolbar() {
    if (!editor) return;
    const bar = $('bp-toolbar');
    const on = (name, active) => {
        const btn = bar.querySelector(`[data-cmd="${name}"]`);
        if (btn) btn.classList.toggle('is-active', !!active);
    };

    on('bold', editor.isActive('bold'));
    on('italic', editor.isActive('italic'));
    on('underline', editor.isActive('underline'));
    on('strike', editor.isActive('strike'));
    on('code', editor.isActive('code'));
    on('blockquote', editor.isActive('blockquote'));
    on('bulletList', editor.isActive('bulletList'));
    on('orderedList', editor.isActive('orderedList'));
    on('highlight', editor.isActive('highlight'));
    on('link', editor.isActive('link'));
    on('align-left', editor.isActive({ textAlign: 'left' }));
    on('align-center', editor.isActive({ textAlign: 'center' }));
    on('align-right', editor.isActive({ textAlign: 'right' }));

    // Block-type select reflects the node under the caret.
    let v = 'paragraph';
    if (editor.isActive('heading', { level: 2 })) v = 'h2';
    else if (editor.isActive('heading', { level: 3 })) v = 'h3';
    else if (editor.isActive('heading', { level: 4 })) v = 'h4';
    $('bp-tb-block').value = v;

    // Current text-colour swatch.
    const c = editor.getAttributes('textStyle').color;
    if (c) {
        $('bp-tb-color-bar').style.background = c;
        if (/^#[0-9a-f]{3,8}$/i.test(c)) $('bp-tb-color').value = c;
    }
}

// ---------------------------------------------------------------------------
// DAM picker modal (reused for featured / OG / inline images)
// ---------------------------------------------------------------------------

let damSelect = null;

function openDam(onSelect) {
    damSelect = onSelect;
    $('bp-dam-modal').hidden = false;
    $('bp-dam-search').value = '';
    drawDamGrid('');
}

function closeDam() { $('bp-dam-modal').hidden = true; damSelect = null; }

function drawDamGrid(term) {
    const grid = $('bp-dam-grid');
    grid.innerHTML = '';
    const q = term.toLowerCase();
    state.assets
        .filter((a) => !q || (a.name || '').toLowerCase().includes(q))
        .forEach((a) => {
            const cell = el('div', 'bp-dam-cell');
            const img = el('img');
            img.src = a.url;
            img.loading = 'lazy';
            cell.append(img, el('span', null, esc(a.name)));
            cell.addEventListener('click', () => { if (damSelect) damSelect(a); closeDam(); });
            grid.append(cell);
        });
    if (!grid.children.length) grid.append(el('div', 'bp-muted-note', 'No images yet — upload one.'));
}

function wireDam() {
    $('bp-dam-close').addEventListener('click', closeDam);
    $('bp-dam-modal').addEventListener('click', (e) => { if (e.target === $('bp-dam-modal')) closeDam(); });
    $('bp-dam-search').addEventListener('input', (e) => drawDamGrid(e.target.value));

    $('bp-dam-upload').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(BOOT.endpoints.assetUpload, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': BOOT.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd,
        });
        const json = await res.json().catch(() => ({}));
        if (res.ok && json.asset) {
            // AssetController returns the public URL under `file_path`.
            const asset = { id: json.asset.id, name: json.asset.name, url: json.asset.file_path, alt: '' };
            state.assets.unshift(asset);
            drawDamGrid($('bp-dam-search').value);
        }
        e.target.value = '';
    });
}

// ---------------------------------------------------------------------------
// Settings modal: featured image, category, tags
// ---------------------------------------------------------------------------

function renderFeatured() {
    const wrap = $('bp-featured');
    wrap.innerHTML = '';
    const a = state.featuredAssetId ? assetById(state.featuredAssetId) : null;
    if (a) {
        const img = el('img');
        img.src = a.url;
        wrap.append(img);
    }
    const row = el('div', 'bp-row');
    const choose = el('button', 'bp-btn bp-btn-sm', a ? 'Replace' : 'Choose image');
    choose.type = 'button';
    choose.addEventListener('click', () => openDam((asset) => { state.featuredAssetId = asset.id; renderFeatured(); markDirty(); }));
    row.append(choose);
    if (a) {
        const rm = el('button', 'bp-btn bp-btn-sm', 'Remove');
        rm.type = 'button';
        rm.addEventListener('click', () => { state.featuredAssetId = null; renderFeatured(); markDirty(); });
        row.append(rm);
    }
    wrap.append(row);
}

function buildCategorySelect() {
    const sel = $('bp-category');
    (BOOT.categories || []).forEach((c) => {
        const o = el('option', null, esc(c.name));
        o.value = c.id;
        if (Number(state.categoryId) === Number(c.id)) o.selected = true;
        sel.append(o);
    });
    sel.addEventListener('change', () => { state.categoryId = sel.value ? Number(sel.value) : null; markDirty(); });

    $('bp-add-category').addEventListener('click', async () => {
        const name = $('bp-new-category').value.trim();
        if (!name) return;
        const { ok, json } = await api(BOOT.endpoints.categoriesStore, 'POST', { name });
        if (ok && json.category) {
            const o = el('option', null, esc(json.category.name));
            o.value = json.category.id;
            sel.append(o);
            sel.value = json.category.id;
            state.categoryId = Number(json.category.id);
            $('bp-new-category').value = '';
            markDirty();
        }
    });
}

function renderTagChips() {
    const box = $('bp-tag-chips');
    box.innerHTML = '';
    state.tagIds.forEach((id) => {
        const tag = state.tags.find((t) => Number(t.id) === Number(id));
        if (!tag) return;
        const chip = el('span', 'bp-chip', esc(tag.name));
        const x = el('button', null, '×');
        x.type = 'button';
        x.addEventListener('click', () => {
            state.tagIds = state.tagIds.filter((t) => Number(t) !== Number(id));
            renderTagChips(); markDirty();
        });
        chip.append(x);
        box.append(chip);
    });
}

function wireTagCreate() {
    const add = async () => {
        const name = $('bp-new-tag').value.trim();
        if (!name) return;
        const { ok, json } = await api(BOOT.endpoints.tagsStore, 'POST', { name });
        if (ok && json.tag) {
            if (!state.tags.some((t) => Number(t.id) === Number(json.tag.id))) state.tags.push(json.tag);
            if (!state.tagIds.some((t) => Number(t) === Number(json.tag.id))) state.tagIds.push(Number(json.tag.id));
            $('bp-new-tag').value = '';
            renderTagChips(); markDirty();
        }
    };
    $('bp-add-tag').addEventListener('click', add);
    $('bp-new-tag').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
}

// ---------------------------------------------------------------------------
// Slug
// ---------------------------------------------------------------------------

function slugify(s) {
    return String(s || '').toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

function applySlugLock() {
    const input = $('bp-slug');
    if (state.slugLocked) {
        input.disabled = true;
        $('bp-slug-lock').hidden = false;
    } else {
        input.disabled = false;
        $('bp-slug-lock').hidden = true;
    }
}

// ---------------------------------------------------------------------------
// Save / autosave
// ---------------------------------------------------------------------------

function setStatusIndicator(stateName, text) {
    const ind = $('bp-autosave-status');
    ind.dataset.state = stateName;
    ind.textContent = text;
}

function markDirty() {
    setStatusIndicator('editing', 'Editing…');
    clearTimeout(state.autosaveTimer);
    state.autosaveTimer = setTimeout(() => persist('autosave'), 5000);
}

function collectPayload(kind) {
    const title = $('bp-title').value.trim();
    const payload = {
        title: title || (state.postUuid ? undefined : 'Untitled draft'),
        body_json: serialize(),
        category_id: state.categoryId,
        tag_ids: state.tagIds,
        featured_asset_id: state.featuredAssetId,
        // SEO workspace (blog_post_seo) — always included so every autosave/save
        // cycle keeps it current, same as body_json/tag_ids.
        seo: SeoPanel ? SeoPanel.collect() : {},
    };
    if (!state.slugLocked) {
        const slug = $('bp-slug').value.trim();
        if (slug) payload.slug = slug;
    }
    const scheduledAt = $('bp-scheduled-at').value;
    if (scheduledAt) payload.scheduled_at = scheduledAt;

    // Only the explicit Save / Publish paths change publish state; draft &
    // autosave never do (the server also strips status on those routes).
    if (kind === 'save') payload.status = $('bp-status').value;
    if (kind === 'publish') payload.status = $('bp-scheduled-at').value ? 'scheduled' : 'published';
    return payload;
}

function adoptPost(post) {
    if (!post) return;
    if (post.uuid && !state.postUuid) {
        state.postUuid = post.uuid;
        const editUrl = BOOT.endpoints.editPage.replace('__UUID__', state.postUuid);
        window.history.replaceState({}, '', editUrl);
    }
    if (post.status) $('bp-status').value = post.status;
    if (post.slug) $('bp-slug').value = post.slug;
    if (post.slug_locked && !state.slugLocked) { state.slugLocked = true; }
    applySlugLock();

    // Keep BOOT.post fresh (e.g. the auto-filled excerpt used by the SEO panel),
    // then tell the SEO module a save landed so its previews can refresh.
    BOOT.post = { ...(BOOT.post || {}), ...post };
    document.dispatchEvent(new CustomEvent('bp:saved', { detail: post }));

    if (PublishPanel) PublishPanel.refreshButton();
}

function stampSaved() {
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setStatusIndicator('saved', 'Saved ' + time);
}

// Returns true when the save round-trip succeeded (the publish flow chains a
// website publish off a true result).
async function persist(kind) {
    if (state.saving) return false;
    state.saving = true;
    clearTimeout(state.autosaveTimer);
    setStatusIndicator('saving', 'Saving…');
    clearErrors();

    const payload = collectPayload(kind);

    try {
        // Step 1 — materialise the draft on first save. `store` deliberately
        // never publishes (status stripped), so a fresh post always lands as a
        // safe draft; a requested publish is then applied in step 2.
        if (!state.postUuid) {
            const createPayload = { ...payload };
            delete createPayload.status;
            const r = await api(BOOT.endpoints.store, 'POST', createPayload);
            if (!r.ok) {
                if (r.status === 422) showErrors(r.json.errors || {});
                setStatusIndicator('error', 'Not saved');
                return false;
            }
            adoptPost(r.json.post);
            if (kind === 'autosave' || kind === 'draft') { stampSaved(); return true; }
            // save/publish: fall through to apply the status change.
        }

        // Step 2 — routine save against the existing post.
        let url, method;
        if (kind === 'autosave') { url = withUuid(BOOT.endpoints.autosave); method = 'POST'; }
        else if (kind === 'draft') { url = withUuid(BOOT.endpoints.draft); method = 'POST'; }
        else { url = withUuid(BOOT.endpoints.update); method = 'PUT'; } // save / publish

        const r = await api(url, method, payload);
        if (!r.ok) {
            if (r.status === 422) showErrors(r.json.errors || {});
            setStatusIndicator('error', 'Not saved');
            return false;
        }
        adoptPost(r.json.post);
        stampSaved();
        return true;
    } catch (err) {
        setStatusIndicator('error', 'Save failed');
        return false;
    } finally {
        state.saving = false;
    }
}

function clearErrors() { const b = $('bp-error'); b.hidden = true; b.textContent = ''; }
function showErrors(errors) {
    const lines = [];
    Object.values(errors).forEach((arr) => (Array.isArray(arr) ? arr : [arr]).forEach((m) => lines.push('• ' + m)));
    const b = $('bp-error');
    b.textContent = lines.join('\n') || 'Please fix the errors and try again.';
    b.hidden = false;
}

// ---------------------------------------------------------------------------
// Hydration + wiring
// ---------------------------------------------------------------------------

function hydrateMeta() {
    const post = BOOT.post;
    if (post) {
        $('bp-title').value = post.title || '';
        $('bp-slug').value = post.slug || '';
        if (post.status) $('bp-status').value = post.status;
    }
    applySlugLock();
    renderFeatured();
    renderTagChips();
}

function wireToolbar() {
    $('bp-title').addEventListener('input', () => {
        if (!state.slugLocked && !state.slugDirty) $('bp-slug').value = slugify($('bp-title').value);
        markDirty();
    });
    $('bp-slug').addEventListener('input', () => { state.slugDirty = true; markDirty(); });

    $('bp-save-draft').addEventListener('click', () => persist('draft'));
    $('bp-save').addEventListener('click', () => persist('save'));
    $('bp-publish').addEventListener('click', async () => {
        const scheduled = !!$('bp-scheduled-at').value;
        const site = BOOT.publishTarget === 'wordpress'
            ? 'your connected WordPress site'
            : 'Dentfluence only (no website connected yet — it stays in the app)';
        const msg = scheduled
            ? `Schedule this post? It will be marked Scheduled in Dentfluence and queued to publish to ${site} at the chosen date.`
            : `Publish this post? This locks the URL, marks it Published in Dentfluence, and pushes it to ${site}.`;
        if (!window.confirm(msg)) return;

        // Save + set the Dentfluence status first; only then push to the website.
        const saved = await persist('publish');
        if (saved && PublishPanel) PublishPanel.publishToWebsite();
    });

    // Flush a pending autosave when leaving/hiding the page.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && state.postUuid) {
            clearTimeout(state.autosaveTimer);
        }
    });
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

buildCategorySelect();
wireTagCreate();
wireDam();
wireToolbar();
hydrateMeta();
mountEditor(computeInitialHtml());
setStatusIndicator(BOOT.post ? 'saved' : 'idle', BOOT.post ? 'Loaded' : 'Not saved yet');

// SEO panel wiring — handed only the four things it needs (autosave scheduling,
// the shared DAM picker, live asset lookup, and the live featured-image id for
// its OG-image fallback). It reads #bp-title/#bp-slug directly.
SeoPanel = initSeoPanel({
    markDirty,
    openDam,
    assetById,
    getFeaturedAssetId: () => state.featuredAssetId,
});

// Website publishing panel — handed only the live post uuid + editorial status.
PublishPanel = initPublishPanel({
    getPostUuid: () => state.postUuid,
    getStatus: () => $('bp-status').value,
});

// Re-evaluate the publish button whenever the status selector changes.
$('bp-status').addEventListener('change', () => PublishPanel && PublishPanel.refreshButton());
