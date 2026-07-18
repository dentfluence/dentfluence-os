@extends('marketing.layouts.app')

@section('page-title', 'Marketing — Blog Editor')

{{--
    Blog Marketing Hub — block editor (Wave 1 Slice 3).
    ------------------------------------------------------------------------
    Shared by GET marketing.blog.create (new) and marketing.blog.edit.
    The canonical content is block-JSON (App\Services\Blog\BlogBlockSchema);
    this page hydrates the editor from body_json and serialises back to it on
    save/autosave. TipTap (pinned ESM CDN — see public/js/blog/blog-editor.js)
    powers rich inline text inside paragraph blocks only; the block canvas and
    every other block type are driven by the editor module. body_json is the
    source of truth — the server renders the final HTML (BlogBlockRenderer),
    never the client.

    SEO panel is intentionally a placeholder here — it lands in Slice 4
    (anchor: #bp-seo-anchor).
--}}
@section('marketing-content')
<div id="blog-editor-root" class="bp-wrap">

    {{-- ── Toolbar: back / autosave status / status + save actions ── --}}
    <div class="bp-topbar">
        <a href="{{ route('marketing.blog.index') }}" class="bp-back">&larr; Blog</a>

        <span id="bp-autosave-status" class="bp-autosave" data-state="idle">Not saved yet</span>

        <div class="bp-actions">
            <button type="button" id="bp-settings-open" class="bp-btn bp-settings-btn" aria-haspopup="dialog">&#9881; Settings</button>
            <select id="bp-status" class="bp-select bp-status-select" aria-label="Status">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="button" id="bp-save-draft" class="bp-btn">Save draft</button>
            <button type="button" id="bp-save" class="bp-btn bp-btn-primary">Save</button>
            <button type="button" id="bp-publish" class="bp-btn bp-btn-accent">Publish</button>
        </div>
    </div>

    {{-- Validation / error banner (surfaced from 422 responses) --}}
    <div id="bp-error" class="bp-error" hidden></div>

    <div class="bp-layout">
        {{-- ═══════════════ Main writing column ═══════════════ --}}
        <div class="bp-main">
          <div class="bp-doc">
            <input type="text" id="bp-title" class="bp-title-input" placeholder="Post title" autocomplete="off">

            <div class="bp-slug-row" id="bp-slug-row">
                <span class="bp-slug-prefix">/blog/</span>
                <input type="text" id="bp-slug" class="bp-slug-input" placeholder="url-slug" autocomplete="off">
                <span class="bp-slug-lock" id="bp-slug-lock" hidden title="The URL locks once a post is first published.">locked</span>
            </div>

            {{-- Blocks render here (built by the editor module) --}}
            <div id="bp-canvas" class="bp-canvas" aria-label="Content blocks"></div>

            <div class="bp-add-wrap">
                <button type="button" id="bp-add-block" class="bp-add-btn">+ Add block</button>
                <div id="bp-add-menu" class="bp-add-menu" hidden role="menu"></div>
            </div>
          </div>{{-- /.bp-doc --}}
        </div>{{-- /.bp-main --}}
    </div>{{-- /.bp-layout --}}

    {{-- ═══════════════ Settings modal ═══════════════
         The entire meta sidebar now lives here as three tabs (Details /
         SEO & Social / Publishing), opened by the "⚙ Settings" toolbar button.
         Every element id is PRESERVED and stays in the DOM at page load (the
         modal is only `hidden`, never removed) so blog-editor.js / blog-seo.js /
         blog-publish.js keep binding to them with zero JS changes.
         z-index 135 sits BELOW the DAM picker modal (#bp-dam-modal, 140) so the
         image picker — opened from the featured/OG pickers that now live inside
         this modal — still layers ABOVE it and works. --}}
    <div id="bp-settings-modal" class="bp-settings-modal" hidden role="dialog" aria-modal="true" aria-label="Post settings">
        <div class="bp-settings-card">
            <div class="bp-settings-head">
                <strong>Post settings</strong>
                <button type="button" id="bp-settings-close" class="bp-icon-btn" aria-label="Close">&times;</button>
            </div>
            <div class="bp-settings-tabs" role="tablist">
                <button type="button" class="bp-settings-tab is-active" data-tab="details" role="tab">Details</button>
                <button type="button" class="bp-settings-tab" data-tab="seo" role="tab">SEO &amp; Social</button>
                <button type="button" class="bp-settings-tab" data-tab="publishing" role="tab">Publishing</button>
            </div>
            <div class="bp-settings-body">

              {{-- ── Tab: Details (featured image + category + tags + schedule) ── --}}
              <div class="bp-settings-pane" data-pane="details" role="tabpanel">
            <section class="bp-panel">
                <h3 class="bp-panel-h">Featured image</h3>
                <div id="bp-featured" class="bp-featured"></div>
            </section>

            <section class="bp-panel">
                <h3 class="bp-panel-h">Category</h3>
                <select id="bp-category" class="bp-select bp-block-select">
                    <option value="">— None —</option>
                </select>
                <div class="bp-inline-create">
                    <input type="text" id="bp-new-category" class="bp-inline-input" placeholder="New category…">
                    <button type="button" id="bp-add-category" class="bp-btn bp-btn-sm">Add</button>
                </div>
            </section>

            <section class="bp-panel">
                <h3 class="bp-panel-h">Tags</h3>
                <div id="bp-tag-chips" class="bp-chips"></div>
                <div class="bp-inline-create">
                    <input type="text" id="bp-new-tag" class="bp-inline-input" placeholder="Add tag…">
                    <button type="button" id="bp-add-tag" class="bp-btn bp-btn-sm">Add</button>
                </div>
            </section>

            <section class="bp-panel">
                <h3 class="bp-panel-h">Schedule</h3>
                <label class="bp-field-label" for="bp-scheduled-at">Publish date (for “scheduled”)</label>
                <input type="datetime-local" id="bp-scheduled-at" class="bp-inline-input bp-full">
            </section>
              </div>{{-- /Tab: Details --}}

              {{-- ── Tab: SEO & Social ──
                Wave 1 Slice 4 — inline SEO workspace. Persists through the same
                `seo` payload the editor already sends on save/autosave (blog_post_seo,
                fully independent of any publishing adapter — see blog-seo.js header).
                Kept as its own self-contained module (public/js/blog/blog-seo.js) so
                it is never tangled into the block-canvas logic above.
              --}}
              <div class="bp-settings-pane" data-pane="seo" role="tabpanel" hidden>
            <section class="bp-panel" id="bp-seo-anchor">
                <h3 class="bp-panel-h">SEO</h3>

                <div class="bp-seo-block">
                    <label class="bp-field-label" for="bp-seo-focus">Focus keyword</label>
                    <input type="text" id="bp-seo-focus" class="bp-inline-input bp-full" placeholder="e.g. root canal treatment cost" autocomplete="off">

                    <label class="bp-field-label" style="margin-top:10px;">Secondary keywords</label>
                    <div id="bp-seo-keywords" class="bp-chips"></div>
                    <div class="bp-inline-create">
                        <input type="text" id="bp-seo-keyword-input" class="bp-inline-input" placeholder="Add keyword, press Enter…" autocomplete="off">
                        <button type="button" id="bp-seo-keyword-add" class="bp-btn bp-btn-sm">Add</button>
                    </div>
                </div>

                <div class="bp-seo-block">
                    <div class="bp-seo-field-head">
                        <label class="bp-field-label" for="bp-seo-meta-title">Meta title</label>
                        <span id="bp-seo-title-count" class="bp-count">0 / 60</span>
                    </div>
                    <input type="text" id="bp-seo-meta-title" class="bp-inline-input bp-full" placeholder="Defaults to the post title" autocomplete="off">
                </div>

                <div class="bp-seo-block">
                    <div class="bp-seo-field-head">
                        <label class="bp-field-label" for="bp-seo-meta-desc">Meta description</label>
                        <span id="bp-seo-desc-count" class="bp-count">0 / 160</span>
                    </div>
                    <textarea id="bp-seo-meta-desc" class="bp-textarea bp-full" placeholder="Defaults to the excerpt"></textarea>
                </div>

                <div class="bp-seo-block">
                    <label class="bp-field-label" for="bp-seo-slug">URL slug</label>
                    <div class="bp-slug-row bp-seo-slug-row">
                        <span class="bp-slug-prefix">/blog/</span>
                        <input type="text" id="bp-seo-slug" class="bp-slug-input" autocomplete="off">
                    </div>
                    <p class="bp-muted-note" id="bp-seo-slug-note" hidden>The URL locks once this post is first published — edit it above.</p>
                </div>

                <div class="bp-seo-block">
                    <label class="bp-field-label" for="bp-seo-canonical">Canonical URL</label>
                    <input type="text" id="bp-seo-canonical" class="bp-inline-input bp-full" placeholder="Leave blank to use this post's own URL" autocomplete="off">
                </div>

                <div class="bp-seo-block">
                    <label class="bp-field-label">Robots</label>
                    <div class="bp-seo-toggle-row">
                        <label class="bp-seo-radio"><input type="radio" name="bp-seo-robots" id="bp-seo-index" value="index"> Index</label>
                        <label class="bp-seo-radio"><input type="radio" name="bp-seo-robots" id="bp-seo-noindex" value="noindex"> NoIndex</label>
                    </div>
                </div>

                <div class="bp-seo-block">
                    <h4 class="bp-seo-sub-h">Search preview</h4>
                    <div class="bp-serp" id="bp-serp-preview">
                        <div class="bp-serp-url" id="bp-serp-url">yourclinic.com &rsaquo; blog &rsaquo; url-slug</div>
                        <div class="bp-serp-title" id="bp-serp-title">Post title</div>
                        <div class="bp-serp-desc" id="bp-serp-desc">Meta description preview appears here as you type…</div>
                    </div>
                </div>

                <hr class="bp-seo-divider">
                <h3 class="bp-panel-h">Social / Open Graph</h3>

                <div class="bp-seo-block">
                    <label class="bp-field-label">OG image</label>
                    <div id="bp-og-image" class="bp-featured"></div>
                </div>

                <div class="bp-seo-block">
                    <label class="bp-field-label" for="bp-seo-og-title">OG title</label>
                    <input type="text" id="bp-seo-og-title" class="bp-inline-input bp-full" placeholder="Defaults to meta title / post title" autocomplete="off">
                </div>

                <div class="bp-seo-block">
                    <label class="bp-field-label" for="bp-seo-og-desc">OG description</label>
                    <textarea id="bp-seo-og-desc" class="bp-textarea bp-full" placeholder="Defaults to meta description / post title"></textarea>
                </div>

                <div class="bp-seo-block">
                    <h4 class="bp-seo-sub-h">Social preview</h4>
                    <div class="bp-og-card" id="bp-og-card">
                        <div class="bp-og-card-img" id="bp-og-card-img"></div>
                        <div class="bp-og-card-body">
                            <div class="bp-og-card-domain" id="bp-og-card-domain">yourclinic.com</div>
                            <div class="bp-og-card-title" id="bp-og-card-title">Post title</div>
                            <div class="bp-og-card-desc" id="bp-og-card-desc">Description preview appears here…</div>
                        </div>
                    </div>
                </div>

                {{-- ════════ Modular extension anchors — future slices mount here ════════
                     Each is an empty, hidden placeholder; a later slice replaces `hidden`
                     with real content for its section. Do not remove these ids. ════════ --}}
                <div id="bp-seo-ai-anchor" class="bp-seo-future" hidden><!-- Wave 2: AI SEO recommendations (Ollama) --></div>
                <div id="bp-seo-readability-anchor" class="bp-seo-future" hidden><!-- Wave 2: Readability score --></div>
                <div id="bp-seo-score-anchor" class="bp-seo-future" hidden><!-- Wave 2: SEO score --></div>
                <div id="bp-seo-links-anchor" class="bp-seo-future" hidden><!-- Wave 2: Internal link suggestions --></div>
                <div id="bp-seo-schema-anchor" class="bp-seo-future" hidden><!-- Wave 2/6: Schema markup --></div>
                <div id="bp-seo-searchconsole-anchor" class="bp-seo-future" hidden><!-- Wave 3: Search Console integration --></div>
                <div id="bp-seo-ranking-anchor" class="bp-seo-future" hidden><!-- Wave 4: Keyword ranking --></div>
            </section>
              </div>{{-- /Tab: SEO & Social --}}

              {{-- ── Tab: Publishing ──
                Wave 1 Slice 6 — website publishing status panel. Driven by the
                blog_publications ledger through the WebsitePublishAdapter layer
                (WordPress → queued; standalone when no site is connected). Shows
                per-target status + external link, a Retry button on failure and a
                "Remove from site" action. Owned by public/js/blog/blog-publish.js;
                content is adapter-independent (this panel never talks to a site).
              --}}
              <div class="bp-settings-pane" data-pane="publishing" role="tabpanel" hidden>
            <section class="bp-panel" id="bp-publish-panel">
                <h3 class="bp-panel-h">Website publishing</h3>
                <p class="bp-muted-note" id="bp-publish-hint" style="margin-bottom:9px;"></p>
                <div id="bp-publish-list" class="bp-pub-list"></div>
                <button type="button" id="bp-publish-to-site" class="bp-btn bp-btn-primary bp-full" style="margin-top:10px;" disabled>Publish to website</button>
                <p class="bp-muted-note" id="bp-publish-note" style="margin-top:8px;">Save the post first, then set its status to Published or Scheduled to push it to a connected website.</p>
            </section>
              </div>{{-- /Tab: Publishing --}}

            </div>{{-- /.bp-settings-body --}}
        </div>{{-- /.bp-settings-card --}}
    </div>{{-- /#bp-settings-modal --}}

    {{-- ── DAM image picker modal (reused for featured + inline images) ── --}}
    <div id="bp-dam-modal" class="bp-modal" hidden>
        <div class="bp-modal-card">
            <div class="bp-modal-head">
                <strong>Choose an image</strong>
                <button type="button" id="bp-dam-close" class="bp-icon-btn" aria-label="Close">&times;</button>
            </div>
            <div class="bp-modal-tools">
                <input type="search" id="bp-dam-search" class="bp-inline-input bp-full" placeholder="Search images…">
                <label class="bp-btn bp-btn-sm bp-upload-label">
                    Upload
                    <input type="file" id="bp-dam-upload" accept="image/*" hidden>
                </label>
            </div>
            <div id="bp-dam-grid" class="bp-dam-grid"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Blog editor — plain baseline, plum accents to match the module. */
    .bp-wrap { font-family: 'Inter', sans-serif; color: #1e0a2c; }
    .bp-topbar { display:flex; align-items:center; gap:14px; padding:12px 26px 12px; border-bottom:1px solid rgba(185,92,183,0.14); position:sticky; top:52px; background:#fff; z-index:15; }
    .bp-back { font-size:13px; color:#6a0f70; text-decoration:none; font-weight:500; }
    .bp-autosave { font-size:11.5px; font-weight:500; color:#8a5a8f; background:#f3e9f4; border-radius:999px; padding:3px 11px; }
    .bp-autosave[data-state="saving"] { color:#8a6d0b; background:#fbf3d8; }
    .bp-autosave[data-state="saved"] { color:#256a29; background:#e4f4e5; }
    .bp-autosave[data-state="error"] { color:#a42020; background:#fdecec; }
    .bp-actions { margin-left:auto; display:flex; align-items:center; gap:8px; }
    .bp-select { font-family:inherit; font-size:13px; padding:6px 8px; border:1px solid rgba(185,92,183,0.28); border-radius:8px; background:#fff; color:#1e0a2c; }
    .bp-btn { font-family:inherit; font-size:13px; padding:7px 12px; border:1px solid rgba(185,92,183,0.28); border-radius:8px; background:#fff; color:#1e0a2c; cursor:pointer; }
    .bp-btn:hover { background:#faf5fb; }
    .bp-btn-sm { padding:5px 9px; font-size:12px; }
    .bp-btn-primary { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .bp-btn-primary:hover { background:#560c5b; }
    .bp-btn-accent { background:#b95cb7; border-color:#b95cb7; color:#fff; }
    .bp-btn-accent:hover { background:#a84ea6; }
    .bp-error { background:#fdecec; border:1px solid #f3b6b6; color:#a12020; font-size:12.5px; border-radius:10px; padding:9px 12px; margin:12px 0 0; white-space:pre-line; }

    /* Sidebar removed — the writing column is now a centered, full-width
       "document" card. The meta panels live in the Settings modal instead. */
    .bp-layout { margin-top:18px; }
    .bp-main { max-width:820px; margin:0 auto; min-width:0; }
    .bp-doc { background:#fff; border:1px solid rgba(185,92,183,0.14); border-radius:14px; padding:30px 32px; box-shadow:0 1px 4px rgba(106,15,112,0.05); }
    .bp-side { flex:0 0 288px; } /* legacy — retained but no longer rendered */

    .bp-title-input { width:100%; border:none; outline:none; font-size:28px; font-weight:600; color:#1e0a2c; padding:8px 6px; box-sizing:border-box; }
    .bp-title-input::placeholder { color:#c3aecd; }
    .bp-slug-row { display:flex; align-items:center; gap:2px; margin:2px 0 22px; padding:0 6px; font-size:13px; color:#7a6b88; }
    .bp-slug-prefix { color:#a99bb4; }
    .bp-slug-input { border:none; outline:none; font-size:13px; color:#6a0f70; background:transparent; min-width:120px; }
    .bp-slug-input:disabled { color:#9b8aa6; }
    .bp-slug-lock { font-size:11px; background:#f3e9f4; color:#8a5a8f; border-radius:3px; padding:2px 6px; margin-left:6px; }

    /* Blocks */
    .bp-canvas { display:flex; flex-direction:column; gap:10px; }
    .bp-block { border:1px solid rgba(185,92,183,0.16); border-radius:10px; background:#fff; }
    .bp-block.bp-dragover { border-color:#b95cb7; }
    .bp-block-head { display:flex; align-items:center; gap:6px; padding:6px 8px; border-bottom:1px solid rgba(185,92,183,0.10); background:#faf6fb; border-radius:10px 10px 0 0; }
    .bp-block-type { font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:#8a5a8f; }
    .bp-block-tools { margin-left:auto; display:flex; align-items:center; gap:2px; }
    .bp-drag { cursor:grab; color:#b39cbd; font-size:14px; padding:0 4px; }
    .bp-icon-btn { border:none; background:transparent; cursor:pointer; color:#9b6aad; font-size:14px; padding:3px 6px; border-radius:6px; line-height:1; }
    .bp-icon-btn:hover { background:#efe3f0; color:#6a0f70; }
    .bp-block-body { padding:10px 12px; }

    .bp-input, .bp-textarea, .bp-block-select, .bp-inline-input { font-family:inherit; font-size:14px; color:#1e0a2c; border:1px solid rgba(185,92,183,0.22); border-radius:8px; padding:7px 9px; background:#fff; width:100%; box-sizing:border-box; }
    .bp-textarea { resize:vertical; min-height:64px; }
    .bp-block-select { font-size:13px; }
    .bp-row { display:flex; gap:8px; align-items:center; }
    .bp-row + .bp-row { margin-top:8px; }
    .bp-field-label { display:block; font-size:11px; color:#8a7b96; margin:0 0 3px; }

    /* TipTap paragraph surface — auto-grows with content. min-height keeps a
       comfortable starting size (~4 lines); there is deliberately no
       max-height/overflow anywhere in this block so the box always grows to
       fit what's typed or pasted, never clips, never inner-scrolls. */
    .bp-rt-toolbar { display:flex; gap:4px; margin-bottom:6px; }
    .bp-rt-btn { font-size:12px; min-width:26px; padding:3px 6px; border:1px solid rgba(185,92,183,0.22); border-radius:6px; background:#fff; cursor:pointer; color:#5a4868; }
    .bp-rt-btn.is-active { background:#6a0f70; color:#fff; border-color:#6a0f70; }
    .bp-rt-editor { border:1px solid rgba(185,92,183,0.22); border-radius:8px; padding:8px 10px; min-height:96px; height:auto; overflow:visible; font-size:14px; line-height:1.55; }
    .bp-rt-editor:focus-within { border-color:#b95cb7; }
    .bp-rt-editor .ProseMirror { outline:none; height:auto; min-height:inherit; overflow:visible; white-space:pre-wrap; overflow-wrap:anywhere; }
    .bp-rt-editor .ProseMirror p { margin:0; }
    .bp-rt-editor .ProseMirror a { color:#6a0f70; text-decoration:underline; }

    /* Table block */
    .bp-tbl { border-collapse:collapse; width:100%; }
    .bp-tbl td { border:1px solid rgba(185,92,183,0.18); padding:0; }
    .bp-tbl input { border:none; outline:none; width:100%; padding:6px 8px; font-family:inherit; font-size:13px; box-sizing:border-box; }
    .bp-tbl tr:first-child input { font-weight:600; background:#faf6fb; }
    .bp-tbl-tools { display:flex; gap:6px; margin-top:8px; }

    /* Images */
    .bp-img-preview { max-width:100%; max-height:220px; border-radius:8px; display:block; margin-bottom:8px; border:1px solid rgba(185,92,183,0.16); }
    .bp-featured { }
    .bp-featured img { width:100%; border-radius:8px; display:block; margin-bottom:8px; border:1px solid rgba(185,92,183,0.16); }

    /* FAQ */
    .bp-faq-item { border:1px solid rgba(185,92,183,0.14); border-radius:5px; padding:8px; margin-bottom:8px; }

    /* Add-block menu */
    .bp-add-wrap { position:relative; margin-top:14px; }
    .bp-add-btn { font-family:inherit; font-size:13px; color:#6a0f70; background:#fff; border:1px dashed rgba(185,92,183,0.4); border-radius:10px; padding:9px 14px; cursor:pointer; width:100%; }
    .bp-add-btn:hover { background:#faf5fb; }
    .bp-add-menu { position:absolute; top:calc(100% + 4px); left:0; background:#fff; border:1px solid rgba(185,92,183,0.2); border-radius:12px; box-shadow:0 8px 24px rgba(60,10,60,0.12); padding:6px; z-index:30; display:grid; grid-template-columns:1fr 1fr; gap:2px; min-width:260px; }
    .bp-add-item { text-align:left; font-size:13px; padding:8px 10px; border:none; background:transparent; cursor:pointer; border-radius:8px; color:#1e0a2c; }
    .bp-add-item:hover { background:#f5ecf6; }

    /* Sidebar */
    .bp-panel { border:1px solid rgba(185,92,183,0.14); border-radius:12px; padding:14px; margin-bottom:14px; background:#fff; }
    .bp-panel:last-child { margin-bottom:0; }
    .bp-panel-muted { background:#faf7fb; }
    .bp-panel-h { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#8a5a8f; margin:0 0 9px; }
    .bp-full { width:100%; box-sizing:border-box; }
    .bp-inline-create { display:flex; gap:6px; margin-top:8px; }
    .bp-chips { display:flex; flex-wrap:wrap; gap:6px; }
    .bp-chip { display:inline-flex; align-items:center; gap:5px; background:#f3e9f4; color:#6a0f70; font-size:12px; border-radius:12px; padding:3px 9px; }
    .bp-chip button { border:none; background:transparent; color:#8a5a8f; cursor:pointer; font-size:13px; line-height:1; padding:0; }
    .bp-muted-note { font-size:12px; color:#9b8aa6; line-height:1.5; margin:0; }
    .bp-choose-btn { font-size:12.5px; }

    /* Website publishing panel (Slice 6) */
    .bp-pub-list { display:flex; flex-direction:column; gap:8px; }
    .bp-pub-row { border:1px solid rgba(185,92,183,0.16); border-radius:10px; padding:9px 10px; background:#fdfaff; }
    .bp-pub-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .bp-pub-target { font-size:12px; font-weight:600; color:#5a4868; text-transform:capitalize; }
    .bp-pub-state { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:2px 8px; border-radius:10px; }
    .bp-pub-state[data-s="published"] { background:rgba(22,163,74,0.12); color:#16a34a; }
    .bp-pub-state[data-s="publishing"], .bp-pub-state[data-s="pending"] { background:#fdf6e3; color:#b8860b; }
    .bp-pub-state[data-s="failed"] { background:#fdecec; color:#c62828; }
    .bp-pub-state[data-s="deleted"] { background:#f0f0f0; color:#78716c; }
    .bp-pub-meta { margin-top:6px; font-size:11.5px; color:#7a6b88; line-height:1.5; word-break:break-word; }
    .bp-pub-meta a { color:#6a0f70; }
    .bp-pub-err { color:#c62828; }
    .bp-pub-actions { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; }

    /* SEO panel (Slice 4) */
    .bp-seo-block { margin-bottom:14px; }
    .bp-seo-block:last-of-type { margin-bottom:0; }
    .bp-seo-field-head { display:flex; align-items:baseline; justify-content:space-between; margin-bottom:3px; }
    .bp-seo-field-head .bp-field-label { margin:0; }
    .bp-count { font-size:10.5px; color:#a99bb4; white-space:nowrap; }
    .bp-count-warn { color:#b8860b; }
    .bp-count-good { color:#2e7d32; }
    .bp-count-bad { color:#c62828; }
    .bp-seo-slug-row { margin:0 0 4px; }
    .bp-seo-toggle-row { display:flex; gap:14px; }
    .bp-seo-radio { display:flex; align-items:center; gap:5px; font-size:12.5px; color:#4a3a54; cursor:pointer; }
    .bp-seo-divider { border:none; border-top:1px solid rgba(185,92,183,0.14); margin:16px 0 12px; }
    .bp-seo-sub-h { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#8a5a8f; margin:0 0 8px; }
    .bp-seo-future { display:none; }

    /* Google search-result mimic */
    .bp-serp { border:1px solid rgba(185,92,183,0.14); border-radius:10px; padding:10px 12px; background:#fff; font-family:Arial,sans-serif; }
    .bp-serp-url { font-size:12.5px; color:#4d5156; margin-bottom:2px; }
    .bp-serp-title { font-size:16px; color:#1a0dab; line-height:1.3; margin-bottom:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bp-serp-desc { font-size:12.5px; color:#4d5156; line-height:1.4; }

    /* OG / social share-card mimic */
    .bp-og-card { border:1px solid rgba(185,92,183,0.18); border-radius:10px; overflow:hidden; background:#fff; }
    .bp-og-card-img { width:100%; aspect-ratio:1.91/1; background:#f3e9f4; display:flex; align-items:center; justify-content:center; }
    .bp-og-card-img img { width:100%; height:100%; object-fit:cover; display:block; }
    .bp-og-card-img-empty::before { content:'No image'; font-size:11px; color:#b39cbd; }
    .bp-og-card-body { padding:9px 11px; border-top:1px solid rgba(185,92,183,0.12); }
    .bp-og-card-domain { font-size:10.5px; text-transform:uppercase; letter-spacing:.03em; color:#8a7b96; margin-bottom:3px; }
    .bp-og-card-title { font-size:13.5px; font-weight:600; color:#1e0a2c; line-height:1.3; margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bp-og-card-desc { font-size:12px; color:#7a6b88; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

    /* Modal */
    .bp-modal { position:fixed; inset:0; background:rgba(30,10,44,0.42); display:flex; align-items:center; justify-content:center; z-index:140; }
    .bp-modal-card { background:#fff; border-radius:14px; width:min(720px,92vw); max-height:82vh; display:flex; flex-direction:column; overflow:hidden; }
    .bp-modal-head { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid rgba(185,92,183,0.14); }
    .bp-modal-tools { display:flex; gap:8px; padding:12px 16px; align-items:center; }
    .bp-upload-label { flex-shrink:0; }
    .bp-dam-grid { padding:0 16px 16px; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
    .bp-dam-cell { border:1px solid rgba(185,92,183,0.16); border-radius:8px; overflow:hidden; cursor:pointer; background:#faf6fb; }
    .bp-dam-cell:hover { border-color:#b95cb7; }
    .bp-dam-cell img { width:100%; height:90px; object-fit:cover; display:block; }
    .bp-dam-cell span { display:block; font-size:11px; color:#7a6b88; padding:4px 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Settings modal — holds the (relocated) meta sidebar as tabs.
       z-index 135 keeps it BELOW the DAM picker (140) so the image picker,
       opened from inside here, still layers above. The [hidden] rule has a
       higher specificity than the base rule so `hidden` reliably hides it
       (the base sets display:flex). */
    .bp-settings-modal { position:fixed; inset:0; background:rgba(30,10,44,0.42); display:flex; align-items:flex-start; justify-content:center; padding:5vh 16px; z-index:135; }
    .bp-settings-modal[hidden] { display:none; }
    .bp-settings-card { background:#fff; border-radius:14px; width:min(560px,94vw); max-height:88vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(30,10,44,0.28); }
    .bp-settings-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid rgba(185,92,183,0.14); }
    .bp-settings-head strong { font-size:15px; color:#1e0a2c; }
    .bp-settings-tabs { display:flex; gap:2px; padding:8px 14px 0; border-bottom:1px solid rgba(185,92,183,0.12); flex-shrink:0; }
    .bp-settings-tab { font-family:inherit; font-size:13px; font-weight:500; color:#7a6b88; background:transparent; border:none; padding:9px 14px; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; border-radius:8px 8px 0 0; }
    .bp-settings-tab:hover { color:#6a0f70; background:#faf5fb; }
    .bp-settings-tab.is-active { color:#6a0f70; font-weight:600; border-bottom-color:#6a0f70; }
    /* Body scrolls INTERNALLY so the SEO tab scrolls within the modal, not the
       page. flex + min-height:0 lets it shrink to the card and scroll rather
       than clip on short viewports. */
    .bp-settings-body { padding:18px 20px; overflow-y:auto; max-height:82vh; flex:1 1 auto; min-height:0; }
    .bp-settings-pane[hidden] { display:none; }
</style>
@endpush

@push('scripts')
{{-- Editor bootstrap: everything the module needs, discovered server-side.
     __UUID__ placeholders are substituted client-side once the draft exists. --}}
<script>
    window.__BLOG_EDITOR__ = {
        post:       @json($post),
        categories: @json($categories),
        tags:       @json($tags),
        assets:     @json($assets),
        blockTypes: @json($blockTypes),
        statuses:   @json($statuses),
        publishTarget: @json($publishTarget),
        csrf:       @json(csrf_token()),
        endpoints: {
            store:           @json(route('marketing.blog.store')),
            update:          @json(route('marketing.blog.update',   ['blog' => '__UUID__'])),
            draft:           @json(route('marketing.blog.draft',    ['blog' => '__UUID__'])),
            autosave:        @json(route('marketing.blog.autosave', ['blog' => '__UUID__'])),
            editPage:        @json(route('marketing.blog.edit',     ['blog' => '__UUID__'])),
            assets:          @json(route('marketing.blog.assets')),
            assetUpload:     @json(route('marketing.assets.upload')),
            categoriesStore: @json(route('marketing.blog.categories.store')),
            tagsStore:       @json(route('marketing.blog.tags.store')),
            publish:            @json(route('marketing.blog.publish',           ['blog' => '__UUID__'])),
            publications:       @json(route('marketing.blog.publications',      ['blog' => '__UUID__'])),
            publicationRetry:   @json(route('marketing.blog.publication.retry', ['blog' => '__UUID__', 'publication' => '__PUB__'])),
            publicationDelete:  @json(route('marketing.blog.publication.destroy',['blog' => '__UUID__', 'publication' => '__PUB__'])),
        },
    };
</script>
<script type="module" src="{{ asset('js/blog/blog-editor.js') }}"></script>

{{-- Settings modal: open/close + tab switching. Plain vanilla, self-contained;
     it never touches the relocated fields (all still bound by the blog-* modules
     by id), only shows/hides the modal and its three tab panes. --}}
<script>
(function () {
    'use strict';
    var modal = document.getElementById('bp-settings-modal');
    if (!modal) return;

    var openBtn  = document.getElementById('bp-settings-open');
    var closeBtn = document.getElementById('bp-settings-close');
    var tabs  = modal.querySelectorAll('.bp-settings-tab');
    var panes = modal.querySelectorAll('.bp-settings-pane');

    function damOpen() {
        var dam = document.getElementById('bp-dam-modal');
        return dam && !dam.hidden; // the image picker is layered above us
    }
    function openModal()  { modal.hidden = false; }
    function closeModal() { modal.hidden = true; }
    function selectTab(name) {
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.dataset.tab === name); });
        panes.forEach(function (p) { p.hidden = (p.dataset.pane !== name); });
    }

    if (openBtn)  openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // Backdrop click closes — unless the DAM picker is open on top of us.
    modal.addEventListener('click', function (e) {
        if (e.target === modal && !damOpen()) closeModal();
    });
    tabs.forEach(function (t) {
        t.addEventListener('click', function () { selectTab(t.dataset.tab); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden && !damOpen()) closeModal();
    });

    selectTab('details');
})();
</script>
@endpush
