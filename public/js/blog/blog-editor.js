/*
 * Blog Marketing Hub — block editor (Wave 1 Slice 3)
 * ===========================================================================
 * Loading path: pinned ESM CDN (esm.sh). The app renders interactive pages
 * with CDN libraries (Alpine, Tailwind, flatpickr are all pulled from CDNs in
 * layouts/app.blade.php) rather than a shared Vite bundle, so TipTap follows
 * the same pattern — no npm install / no second bundler. All @tiptap/*
 * packages are pinned to one version and share a single ProseMirror via the
 * `?deps=@tiptap/pm@…` query so no duplicate-plugin errors occur.
 *
 * Responsibilities:
 *   - Hydrate the editor from canonical block-JSON (body_json is the source of
 *     truth; we never read/write body_html here — the server renders that).
 *   - Drive the block canvas: add / reorder (drag + up/down) / delete, plus a
 *     dedicated editor per block type.
 *   - Serialise editor state back to block-JSON on save/autosave.
 *   - Debounced autosave, manual Save draft / Save / Publish, DAM image picker,
 *     inline category/tag create. Surfaces 422 validation inline.
 *
 * TipTap is used ONLY for the rich inline layer inside paragraph blocks
 * (bold / italic / link — exactly the schema's paragraph contract). Every
 * other block type is a plain structured editor. This keeps the block engine
 * editor-agnostic: paragraph blocks persist `{ html }` (limited inline markup)
 * which BlogBlockRenderer sanitises server-side.
 *
 * SEO panel (Slice 4) lives in the companion module ./blog-seo.js — kept
 * self-contained so it is never tangled into the block-canvas logic above.
 * This file only wires it in three places: importing initSeoPanel, folding
 * its collect() output into the `seo` key of every save/autosave payload,
 * and telling it when a save round-trip lands (bp:saved) so its previews can
 * pick up server-derived values (e.g. the auto-filled excerpt).
 */

import { initSeoPanel } from './blog-seo.js';
import { initPublishPanel } from './blog-publish.js';

const TIPTAP_VERSION = '2.11.5';
const DEPS = '?deps=@tiptap/pm@' + TIPTAP_VERSION;
const cdn = (pkg) => `https://esm.sh/@tiptap/${pkg}@${TIPTAP_VERSION}${DEPS}`;

const [
    { Editor, Extension },
    Document,
    Paragraph,
    Text,
    Bold,
    Italic,
    Link,
    History,
    HardBreak,
] = await Promise.all([
    import(cdn('core')),
    import(cdn('extension-document')).then((m) => m.default),
    import(cdn('extension-paragraph')).then((m) => m.default),
    import(cdn('extension-text')).then((m) => m.default),
    import(cdn('extension-bold')).then((m) => m.default),
    import(cdn('extension-italic')).then((m) => m.default),
    import(cdn('extension-link')).then((m) => m.default),
    import(cdn('extension-history')).then((m) => m.default),
    import(cdn('extension-hard-break')).then((m) => m.default),
]);

// Single-paragraph document so a paragraph block never splits into multiple
// block-level nodes; getHTML() therefore yields exactly one <p>…</p> whose
// inner inline markup we persist.
const InlineDocument = Document.extend({ content: 'paragraph' });

// HardBreak (below) only binds Mod-Enter/Shift-Enter by default. Since this
// document's schema allows exactly one paragraph, plain Enter has nothing to
// split into and is otherwise a no-op — which reads as "the box won't grow
// when I press Enter". This extension makes plain Enter insert a line break
// too, matching what users expect from a normal paragraph field.
const EnterAsHardBreak = Extension.create({
    name: 'enterAsHardBreak',
    addKeyboardShortcuts() {
        return { Enter: () => this.editor.commands.setHardBreak() };
    },
});

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

const BLOCK_LABELS = {
    heading: 'Heading', paragraph: 'Paragraph', image: 'Image', quote: 'Quote',
    table: 'Table', cta: 'Call to action', faq: 'FAQ', divider: 'Divider',
};

// ---------------------------------------------------------------------------
// Editor state (single source of truth mirrored into block-JSON on save)
// ---------------------------------------------------------------------------

const state = {
    postUuid: BOOT.post?.uuid || null,
    slugLocked: !!BOOT.post?.slug_locked,
    slugDirty: false,
    blocks: [],                 // [{ id, type, data }]
    tagIds: [...(BOOT.post?.tag_ids || [])],
    categoryId: BOOT.post?.category_id || null,
    featuredAssetId: BOOT.post?.featured_asset_id || null,
    assets: [...(BOOT.assets || [])],
    tags: [...(BOOT.tags || [])],
    saving: false,
    autosaveTimer: null,
};

const nodes = new Map(); // block id -> { root, editor?(tiptap) }
const assetById = (id) => state.assets.find((a) => Number(a.id) === Number(id));

// Set once during init (see bottom of file) to the object returned by
// blog-seo.js's initSeoPanel(). Kept nullable so collectPayload() is safe to
// call (defensively) before wiring completes.
let SeoPanel = null;

// Set once during init to the object returned by blog-publish.js's
// initPublishPanel(). Nullable so the toolbar's publish handler is safe if a
// click somehow races init.
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

// A block is only persisted when it satisfies the schema's required fields, so
// an in-progress (e.g. image with no source yet) block never fails validation
// on autosave. It stays in the editor and is persisted once completed.
function isPersistable(b) {
    if (b.type === 'image') return !!(b.data.asset_id || b.data.url);
    if (b.type === 'cta') return !!(b.data.label && b.data.url);
    return true;
}

function serialize() {
    return {
        version: 1,
        blocks: state.blocks
            .filter(isPersistable)
            .map((b) => ({ id: b.id, type: b.type, data: b.data })),
    };
}

// ---------------------------------------------------------------------------
// Block factory
// ---------------------------------------------------------------------------

function defaultData(type) {
    switch (type) {
        case 'heading': return { level: 2, text: '' };
        case 'paragraph': return { html: '' };
        case 'image': return { asset_id: null, url: '', alt: '', caption: '' };
        case 'quote': return { text: '', cite: '' };
        case 'table': return { rows: [['', ''], ['', '']] };
        case 'cta': return { label: '', url: '', style: 'button' };
        case 'faq': return { items: [{ q: '', a: '' }] };
        case 'divider': return {};
        default: return {};
    }
}

function addBlock(type, atIndex, initialData) {
    const block = { id: uid(), type, data: initialData || defaultData(type) };
    const index = atIndex == null ? state.blocks.length : atIndex;
    state.blocks.splice(index, 0, block);

    const built = buildBlock(block);
    const canvas = $('bp-canvas');
    const ref = canvas.children[index] || null;
    canvas.insertBefore(built, ref);
    nodes.set(block.id, { root: built });
    if (block.type === 'paragraph') mountParagraphEditor(block, built);
    markDirty();
}

function removeBlock(id) {
    const i = state.blocks.findIndex((b) => b.id === id);
    if (i === -1) return;
    const rec = nodes.get(id);
    if (rec?.editor) rec.editor.destroy();
    rec?.root.remove();
    nodes.delete(id);
    state.blocks.splice(i, 1);
    markDirty();
}

function moveBlock(id, dir) {
    const i = state.blocks.findIndex((b) => b.id === id);
    const j = i + dir;
    if (i === -1 || j < 0 || j >= state.blocks.length) return;
    [state.blocks[i], state.blocks[j]] = [state.blocks[j], state.blocks[i]];
    // Re-attach every root in the new array order (appendChild moves nodes).
    const canvas = $('bp-canvas');
    state.blocks.forEach((b) => canvas.appendChild(nodes.get(b.id).root));
    markDirty();
}

// Build the outer shell (header + body) for a block; delegates the body to a
// per-type renderer.
function buildBlock(block) {
    const root = el('div', 'bp-block');
    root.dataset.id = block.id;
    root.setAttribute('draggable', 'false');

    const head = el('div', 'bp-block-head');
    const drag = el('span', 'bp-drag', '⠿');
    drag.title = 'Drag to reorder';
    const label = el('span', 'bp-block-type', esc(BLOCK_LABELS[block.type] || block.type));
    const tools = el('div', 'bp-block-tools');
    const up = iconBtn('↑', 'Move up', () => moveBlock(block.id, -1));
    const down = iconBtn('↓', 'Move down', () => moveBlock(block.id, 1));
    const del = iconBtn('🗑', 'Delete block', () => removeBlock(block.id));
    tools.append(up, down, del);
    head.append(drag, label, tools);

    const body = el('div', 'bp-block-body');
    renderBody(block, body);

    root.append(head, body);
    setupDrag(root, drag, block);
    return root;
}

function iconBtn(glyph, title, onClick) {
    const b = el('button', 'bp-icon-btn', glyph);
    b.type = 'button';
    b.title = title;
    b.addEventListener('click', onClick);
    return b;
}

// Drag-and-drop reordering via the header handle (up/down buttons remain the
// keyboard-friendly path).
function setupDrag(root, handle, block) {
    handle.addEventListener('mousedown', () => root.setAttribute('draggable', 'true'));
    root.addEventListener('mouseup', () => root.setAttribute('draggable', 'false'));
    root.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', block.id);
        e.dataTransfer.effectAllowed = 'move';
    });
    root.addEventListener('dragover', (e) => { e.preventDefault(); root.classList.add('bp-dragover'); });
    root.addEventListener('dragleave', () => root.classList.remove('bp-dragover'));
    root.addEventListener('drop', (e) => {
        e.preventDefault();
        root.classList.remove('bp-dragover');
        const draggedId = e.dataTransfer.getData('text/plain');
        if (!draggedId || draggedId === block.id) return;
        reorderTo(draggedId, block.id);
    });
}

function reorderTo(draggedId, targetId) {
    const from = state.blocks.findIndex((b) => b.id === draggedId);
    const to = state.blocks.findIndex((b) => b.id === targetId);
    if (from === -1 || to === -1) return;
    const [moved] = state.blocks.splice(from, 1);
    state.blocks.splice(to, 0, moved);
    // Re-attach DOM in the new array order.
    const canvas = $('bp-canvas');
    state.blocks.forEach((b) => canvas.appendChild(nodes.get(b.id).root));
    markDirty();
}

// ---------------------------------------------------------------------------
// Per-type block bodies
// ---------------------------------------------------------------------------

function renderBody(block, body) {
    switch (block.type) {
        case 'heading': return renderHeading(block, body);
        case 'paragraph': return renderParagraph(block, body);
        case 'image': return renderImage(block, body);
        case 'quote': return renderQuote(block, body);
        case 'table': return renderTable(block, body);
        case 'cta': return renderCta(block, body);
        case 'faq': return renderFaq(block, body);
        case 'divider': return renderDivider(block, body);
    }
}

function renderHeading(block, body) {
    const row = el('div', 'bp-row');
    const level = el('select', 'bp-block-select');
    level.style.flex = '0 0 84px';
    [2, 3, 4, 5, 6].forEach((l) => {
        const o = el('option', null, 'H' + l);
        o.value = l;
        if (Number(block.data.level) === l) o.selected = true;
        level.append(o);
    });
    level.addEventListener('change', () => { block.data.level = Number(level.value); markDirty(); });

    const text = el('input', 'bp-input');
    text.type = 'text';
    text.placeholder = 'Heading text';
    text.value = block.data.text || '';
    text.addEventListener('input', () => { block.data.text = text.value; markDirty(); });

    row.append(level, text);
    body.append(row);
}

function renderParagraph(block, body) {
    // Toolbar + mount point; the TipTap instance is attached in
    // mountParagraphEditor once the node is in the DOM.
    const toolbar = el('div', 'bp-rt-toolbar');
    toolbar.dataset.role = 'toolbar';
    const mount = el('div', 'bp-rt-editor');
    mount.dataset.role = 'mount';
    body.append(toolbar, mount);
}

// ---------------------------------------------------------------------------
// Paragraph paste handling
// ---------------------------------------------------------------------------
// Matches the paragraph block's own schema (Bold/Italic/Link marks only —
// see the extensions list below) so what the user sees while editing matches
// what actually gets typed/saved: inline formatting survives, everything
// else (color/font/class/style/spans/divs/headings…) is unwrapped down to
// plain text rather than dropped.
const PASTE_INLINE_TAGS = { strong: 'strong', b: 'strong', em: 'em', i: 'em', a: 'a' };
// Any of these ending is treated as a paragraph boundary (new block), not a
// same-block line break — matches how browsers/Word/Google Docs mark up
// pasted paragraphs (<p>, <div> per line, list items, headings, etc.).
const PASTE_BLOCK_TAGS = new Set(['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'tr', 'section', 'article', 'header', 'footer', 'pre']);

// Walks pasted HTML and returns an array of sanitised inner-HTML fragments —
// one per resulting paragraph block. Never truncates: every text node is kept
// (junk wrapper tags are unwrapped, not deleted).
function sanitizePastedHtmlToFragments(html) {
    const container = document.createElement('div');
    container.innerHTML = html;
    // Word/Google Docs pastes often carry a full document shell; browsers'
    // fragment parser can leave <style>/<script>/<meta> as literal elements
    // in the tree. Drop them outright (not unwrap) so their raw text never
    // leaks into the paragraph.
    container.querySelectorAll('style, script, meta, link, title').forEach((n) => n.remove());

    const fragments = [];
    let current = document.createElement('div');
    let trailingBr = false; // used to collapse a double <br><br> into a paragraph break

    function flush() {
        const inner = current.innerHTML
            .replace(/^(<br\s*\/?>)+/i, '')
            .replace(/(<br\s*\/?>)+$/i, '')
            .trim();
        if (inner) fragments.push(inner);
        current = document.createElement('div');
        trailingBr = false;
    }

    function appendInto(target, node) {
        if (node.nodeType === Node.TEXT_NODE) {
            if (node.textContent) target.appendChild(document.createTextNode(node.textContent));
            trailingBr = false;
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        const tag = node.tagName.toLowerCase();

        if (tag === 'br') {
            if (trailingBr) { flush(); return; } // 2nd consecutive <br> = paragraph break
            target.appendChild(document.createElement('br'));
            trailingBr = true;
            return;
        }

        if (PASTE_BLOCK_TAGS.has(tag)) {
            node.childNodes.forEach((child) => appendInto(current, child));
            flush();
            return;
        }

        if (PASTE_INLINE_TAGS[tag]) {
            const clean = document.createElement(PASTE_INLINE_TAGS[tag]);
            if (tag === 'a') {
                const href = node.getAttribute('href');
                if (href) clean.setAttribute('href', href);
            }
            target.appendChild(clean);
            node.childNodes.forEach((child) => appendInto(clean, child));
            trailingBr = false;
            return;
        }

        // Unknown/junk wrapper (span, font, table, img, style attrs, etc.) —
        // unwrap it: keep its text/children, drop the tag itself.
        node.childNodes.forEach((child) => appendInto(target, child));
    }

    container.childNodes.forEach((child) => appendInto(current, child));
    flush();
    return fragments;
}

// Plain-text fallback (no text/html on the clipboard): blank-line-separated
// blocks of text become separate paragraphs, single newlines become <br>.
function plainTextToFragments(text) {
    return text
        .split(/\r?\n\s*\r?\n/)
        .map((p) => esc(p.trim()).replace(/\r?\n/g, '<br>'))
        .filter((p) => p !== '');
}

function handleParagraphPaste(editor, block, event) {
    const cd = event.clipboardData;
    if (!cd) return false;
    const html = cd.getData('text/html');
    const text = cd.getData('text/plain');
    if (!html && !text) return false;

    const fragments = html ? sanitizePastedHtmlToFragments(html) : plainTextToFragments(text);
    if (!fragments.length) return false; // nothing usable — let the default (no-op) happen

    event.preventDefault();

    // First fragment inserts inline at the cursor, same as a normal paste
    // (replaces any current selection, keeps surrounding text intact).
    editor.chain().focus().insertContent(fragments[0]).run();

    // Any further paragraphs become new sibling blocks right after this one,
    // in order, instead of being merged/lost.
    if (fragments.length > 1) {
        let index = state.blocks.findIndex((b) => b.id === block.id);
        for (let i = 1; i < fragments.length; i++) {
            index += 1;
            addBlock('paragraph', index, { html: fragments[i] });
        }
    }
    return true;
}

function mountParagraphEditor(block, root) {
    const toolbar = root.querySelector('[data-role="toolbar"]');
    const mount = root.querySelector('[data-role="mount"]');

    const initial = block.data.html ? `<p>${block.data.html}</p>`
        : (block.data.text ? `<p>${esc(block.data.text)}</p>` : '<p></p>');

    const editor = new Editor({
        element: mount,
        extensions: [
            InlineDocument,
            Paragraph,
            Text,
            Bold,
            Italic,
            HardBreak,
            EnterAsHardBreak,
            History,
            Link.configure({ openOnClick: false, autolink: false }),
        ],
        content: initial,
        editorProps: {
            // Intercept paste ourselves: the schema allows exactly one
            // paragraph node per block, so ProseMirror's own paste handling
            // would otherwise squash every pasted block-level element (extra
            // <p>/<div>/<h*>/<li>…) together with no separator, reading as
            // "paste collapses into one blob". We sanitise the clipboard HTML
            // down to the paragraph schema's allowed inline tags, keep every
            // paragraph/line break, and — when the paste contained more than
            // one paragraph — split the extras out into their own sibling
            // paragraph blocks instead of losing the structure.
            handlePaste: (view, event) => handleParagraphPaste(editor, block, event),
        },
        onUpdate: ({ editor }) => {
            // Persist inner inline HTML only — BlogBlockRenderer wraps it in <p>
            // and sanitises to the allowed inline tag set.
            const html = editor.getHTML().replace(/^<p>/, '').replace(/<\/p>$/, '');
            block.data = { html };
            markDirty();
        },
    });

    const mk = (glyph, title, run, isActive) => {
        const b = el('button', 'bp-rt-btn', glyph);
        b.type = 'button';
        b.title = title;
        b.addEventListener('click', () => { run(); syncMarks(); editor.commands.focus(); });
        b._active = isActive;
        return b;
    };
    const bBold = mk('B', 'Bold', () => editor.chain().focus().toggleBold().run(), () => editor.isActive('bold'));
    bBold.style.fontWeight = '700';
    const bItalic = mk('i', 'Italic', () => editor.chain().focus().toggleItalic().run(), () => editor.isActive('italic'));
    bItalic.style.fontStyle = 'italic';
    const bLink = mk('🔗', 'Link', () => {
        const prev = editor.getAttributes('link').href || '';
        const url = window.prompt('Link URL', prev);
        if (url === null) return;
        if (url === '') editor.chain().focus().unsetLink().run();
        else editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }, () => editor.isActive('link'));

    const buttons = [bBold, bItalic, bLink];
    toolbar.append(...buttons);
    function syncMarks() { buttons.forEach((b) => b.classList.toggle('is-active', !!b._active())); }
    editor.on('selectionUpdate', syncMarks);
    editor.on('transaction', syncMarks);

    nodes.get(block.id).editor = editor;
}

function renderImage(block, body) {
    const preview = el('div');
    const renderPreview = () => {
        preview.innerHTML = '';
        const a = block.data.asset_id ? assetById(block.data.asset_id) : null;
        const src = a?.url || block.data.url || '';
        if (src) {
            const img = el('img', 'bp-img-preview');
            img.src = src;
            preview.append(img);
        }
    };
    renderPreview();

    const choose = el('button', 'bp-btn bp-btn-sm bp-choose-btn', block.data.asset_id || block.data.url ? 'Replace image' : 'Choose from library');
    choose.type = 'button';
    choose.addEventListener('click', () => openDam((asset) => {
        block.data.asset_id = asset.id;
        block.data.url = '';
        if (!block.data.alt) block.data.alt = asset.alt || '';
        renderPreview();
        alt.value = block.data.alt || '';
        markDirty();
    }));

    const urlRow = el('div', 'bp-row');
    urlRow.style.marginTop = '8px';
    const url = el('input', 'bp-input');
    url.type = 'text';
    url.placeholder = '…or paste an image URL';
    url.value = block.data.url || '';
    url.addEventListener('input', () => {
        block.data.url = url.value.trim();
        if (block.data.url) block.data.asset_id = null;
        renderPreview();
        markDirty();
    });
    urlRow.append(url);

    const alt = el('input', 'bp-input');
    alt.type = 'text';
    alt.placeholder = 'Alt text (accessibility / SEO)';
    alt.value = block.data.alt || '';
    alt.style.marginTop = '8px';
    alt.addEventListener('input', () => { block.data.alt = alt.value; markDirty(); });

    const caption = el('input', 'bp-input');
    caption.type = 'text';
    caption.placeholder = 'Caption (optional)';
    caption.value = block.data.caption || '';
    caption.style.marginTop = '8px';
    caption.addEventListener('input', () => { block.data.caption = caption.value; markDirty(); });

    body.append(preview, choose, urlRow, alt, caption);
}

function renderQuote(block, body) {
    const text = el('textarea', 'bp-textarea');
    text.placeholder = 'Quote text';
    text.value = block.data.text || '';
    text.addEventListener('input', () => { block.data.text = text.value; markDirty(); });

    const cite = el('input', 'bp-input');
    cite.type = 'text';
    cite.placeholder = 'Attribution (optional)';
    cite.value = block.data.cite || '';
    cite.style.marginTop = '8px';
    cite.addEventListener('input', () => { block.data.cite = cite.value; markDirty(); });

    body.append(text, cite);
}

function renderTable(block, body) {
    const wrap = el('div');
    const draw = () => {
        wrap.innerHTML = '';
        const table = el('table', 'bp-tbl');
        block.data.rows.forEach((row, ri) => {
            const tr = el('tr');
            row.forEach((cell, ci) => {
                const td = el('td');
                const inp = el('input');
                inp.type = 'text';
                inp.value = cell;
                inp.placeholder = ri === 0 ? 'Header' : '';
                inp.addEventListener('input', () => { block.data.rows[ri][ci] = inp.value; markDirty(); });
                td.append(inp);
                tr.append(td);
            });
            table.append(tr);
        });
        wrap.append(table);
    };
    draw();

    const tools = el('div', 'bp-tbl-tools');
    const addRow = el('button', 'bp-btn bp-btn-sm', '+ Row');
    addRow.type = 'button';
    addRow.addEventListener('click', () => {
        const cols = block.data.rows[0]?.length || 2;
        block.data.rows.push(Array(cols).fill(''));
        draw(); markDirty();
    });
    const addCol = el('button', 'bp-btn bp-btn-sm', '+ Column');
    addCol.type = 'button';
    addCol.addEventListener('click', () => { block.data.rows.forEach((r) => r.push('')); draw(); markDirty(); });
    const delRow = el('button', 'bp-btn bp-btn-sm', '− Row');
    delRow.type = 'button';
    delRow.addEventListener('click', () => { if (block.data.rows.length > 1) { block.data.rows.pop(); draw(); markDirty(); } });
    const delCol = el('button', 'bp-btn bp-btn-sm', '− Column');
    delCol.type = 'button';
    delCol.addEventListener('click', () => {
        if ((block.data.rows[0]?.length || 0) > 1) { block.data.rows.forEach((r) => r.pop()); draw(); markDirty(); }
    });
    tools.append(addRow, addCol, delRow, delCol);
    body.append(wrap, tools);
}

function renderCta(block, body) {
    const label = el('input', 'bp-input');
    label.type = 'text';
    label.placeholder = 'Button label';
    label.value = block.data.label || '';
    label.addEventListener('input', () => { block.data.label = label.value; markDirty(); });

    const url = el('input', 'bp-input');
    url.type = 'text';
    url.placeholder = 'Destination URL';
    url.value = block.data.url || '';
    url.style.marginTop = '8px';
    url.addEventListener('input', () => { block.data.url = url.value.trim(); markDirty(); });

    const style = el('select', 'bp-block-select');
    style.style.marginTop = '8px';
    [['button', 'Button'], ['link', 'Text link']].forEach(([v, t]) => {
        const o = el('option', null, t);
        o.value = v;
        if ((block.data.style || 'button') === v) o.selected = true;
        style.append(o);
    });
    style.addEventListener('change', () => { block.data.style = style.value; markDirty(); });

    body.append(label, url, style);
}

function renderFaq(block, body) {
    const list = el('div');
    const draw = () => {
        list.innerHTML = '';
        block.data.items.forEach((item, i) => {
            const card = el('div', 'bp-faq-item');
            const q = el('input', 'bp-input');
            q.type = 'text';
            q.placeholder = 'Question';
            q.value = item.q || '';
            q.addEventListener('input', () => { item.q = q.value; markDirty(); });
            const a = el('textarea', 'bp-textarea');
            a.placeholder = 'Answer';
            a.value = item.a || '';
            a.style.marginTop = '6px';
            a.addEventListener('input', () => { item.a = a.value; markDirty(); });
            const rm = el('button', 'bp-btn bp-btn-sm', 'Remove');
            rm.type = 'button';
            rm.style.marginTop = '6px';
            rm.addEventListener('click', () => {
                if (block.data.items.length > 1) { block.data.items.splice(i, 1); draw(); markDirty(); }
            });
            card.append(q, a, rm);
            list.append(card);
        });
    };
    draw();

    const add = el('button', 'bp-btn bp-btn-sm', '+ Question');
    add.type = 'button';
    add.addEventListener('click', () => { block.data.items.push({ q: '', a: '' }); draw(); markDirty(); });
    body.append(list, add);
}

function renderDivider(block, body) {
    body.append(el('div', 'bp-muted-note', 'Horizontal divider'));
}

// ---------------------------------------------------------------------------
// Add-block menu
// ---------------------------------------------------------------------------

function buildAddMenu() {
    const menu = $('bp-add-menu');
    (BOOT.blockTypes || Object.keys(BLOCK_LABELS)).forEach((type) => {
        const b = el('button', 'bp-add-item', esc(BLOCK_LABELS[type] || type));
        b.type = 'button';
        b.addEventListener('click', () => { addBlock(type); menu.hidden = true; });
        menu.append(b);
    });
    $('bp-add-block').addEventListener('click', (e) => { e.stopPropagation(); menu.hidden = !menu.hidden; });
    document.addEventListener('click', (e) => {
        if (!menu.hidden && !menu.contains(e.target) && e.target !== $('bp-add-block')) menu.hidden = true;
    });
}

// ---------------------------------------------------------------------------
// Sidebar: featured image, category, tags, schedule
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
// DAM picker modal
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
        // SEO workspace (blog_post_seo) — always included so every
        // autosave/save cycle keeps it current, same as body_json/tag_ids.
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

    // Keep BOOT.post's server-derived fields fresh (e.g. the auto-filled
    // excerpt used by the SEO panel's meta-description fallback), then tell
    // the SEO module a save landed so it can refresh its previews/lock state.
    BOOT.post = { ...(BOOT.post || {}), ...post };
    document.dispatchEvent(new CustomEvent('bp:saved', { detail: post }));

    // The post may have just gained a uuid or changed status — let the website
    // publishing panel re-evaluate whether "Publish to website" is available.
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
            // Autosave/draft are complete once the content is stored.
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

function hydrate() {
    const post = BOOT.post;
    if (post) {
        $('bp-title').value = post.title || '';
        $('bp-slug').value = post.slug || '';
        if (post.status) $('bp-status').value = post.status;
        const blocks = post.body_json?.blocks || [];
        blocks.forEach((b) => {
            const block = { id: b.id || uid(), type: b.type, data: b.data || defaultData(b.type) };
            // Only hydrate types this editor knows; unknown/future types are
            // preserved in state (round-tripped) but rendered as a read-only note.
            state.blocks.push(block);
            const built = (BLOCK_LABELS[block.type]) ? buildBlock(block) : buildUnknownBlock(block);
            $('bp-canvas').append(built);
            nodes.set(block.id, { root: built });
            if (block.type === 'paragraph') mountParagraphEditor(block, built);
        });
        setStatusIndicator('saved', 'Loaded');
    }
    applySlugLock();
    renderFeatured();
    renderTagChips();
}

// Preserve (round-trip) block types this V1 editor doesn't render, so opening
// and saving a doc authored by a newer editor never drops content.
function buildUnknownBlock(block) {
    const root = el('div', 'bp-block');
    root.dataset.id = block.id;
    const head = el('div', 'bp-block-head');
    head.append(el('span', 'bp-block-type', esc(block.type)));
    const body = el('div', 'bp-block-body');
    body.append(el('div', 'bp-muted-note', 'This block type isn’t editable in this view; it will be preserved on save.'));
    root.append(head, body);
    return root;
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

        // Save + set the Dentfluence status first; only then push to the
        // website (so the ledger reflects the just-saved content/status).
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

buildAddMenu();
buildCategorySelect();
wireTagCreate();
wireDam();
wireToolbar();
hydrate();

// SEO panel wiring — deliberately handed only the four things it needs from
// this module (autosave scheduling, the shared DAM picker, live asset
// lookup, and the live featured-image id for its OG-image fallback). It
// reads #bp-title/#bp-slug directly and owns #bp-seo-anchor's DOM itself.
SeoPanel = initSeoPanel({
    markDirty,
    openDam,
    assetById,
    getFeaturedAssetId: () => state.featuredAssetId,
});

// Website publishing panel (Slice 6). Handed only what it needs: the live post
// uuid and editorial status. It owns #bp-publish-panel's DOM and talks to the
// blog_publications ledger endpoints itself — content/SEO stay adapter-agnostic.
PublishPanel = initPublishPanel({
    getPostUuid: () => state.postUuid,
    getStatus: () => $('bp-status').value,
});

// Re-evaluate the publish button whenever the status selector changes (only
// Published/Scheduled posts can be pushed to a website).
$('bp-status').addEventListener('change', () => PublishPanel && PublishPanel.refreshButton());
