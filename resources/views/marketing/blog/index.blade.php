@extends('marketing.layouts.app')

@section('page-title', 'Marketing — Blog')

{{--
    Blog Marketing Hub — CMS list (Wave 1 Slice 5).
    ------------------------------------------------------------------------
    Replaces the Slice-1 placeholder. Status filter + search are plain GET
    query params (status/q) handled server-side in BlogController::index()
    via BlogPostService::listForClinic() — no client-side filtering, so the
    page works with JS disabled and paginates correctly. Row actions
    (duplicate/archive/unarchive/delete) and the version-history modal are
    the only parts that need JS; see public/js/blog/blog-list.js.
--}}
@section('marketing-content')
<div id="blog-list-root" class="bl-wrap">

    {{-- ── Header ── --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond', serif; font-size:26px; font-weight:700; color:#1e0a2c; margin:0 0 3px; line-height:1.1;">Blog</h1>
            <p style="font-family:'Inter', sans-serif; font-size:12.5px; font-weight:300; color:#7a6884; margin:0;">Write, organize and schedule blog content.</p>
        </div>
        <a href="{{ route('marketing.blog.create') }}" style="
            display:inline-flex; align-items:center; gap:6px;
            background:linear-gradient(135deg, #6a0f70 0%, #b95cb7 100%);
            color:#fff; font-family:'Inter', sans-serif; font-size:13px; font-weight:500;
            padding:9px 18px; border-radius:6px; text-decoration:none;
            box-shadow:0 2px 8px rgba(106,15,112,0.22); transition:opacity 150ms;
        " onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New post
        </a>
    </div>

    {{-- ── Filter row: status tabs (left) + search (right) ── --}}
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px;">

        {{-- Status tab pills — plain <a> links carrying the query string; the
             page reloads server-rendered, no client-side filtering. --}}
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            @php
                $statusTabs = [
                    'all'       => 'All',
                    'draft'     => 'Draft',
                    'scheduled' => 'Scheduled',
                    'published' => 'Published',
                    'archived'  => 'Archived',
                ];
            @endphp
            @foreach ($statusTabs as $key => $label)
                @php
                    $isActive = $activeStatus === $key;
                    $count    = $statusCounts[$key] ?? 0;
                    $href     = route('marketing.blog.index', array_filter([
                        'status' => $key !== 'all' ? $key : null,
                        'q'      => $search !== '' ? $search : null,
                    ]));
                @endphp
                <a href="{{ $href }}" style="
                    display:inline-flex; align-items:center; gap:6px;
                    padding:6px 13px; border-radius:20px;
                    font-family:'Inter', sans-serif; font-size:12.5px;
                    font-weight:{{ $isActive ? '600' : '500' }};
                    color:{{ $isActive ? '#fff' : '#5a4868' }};
                    background:{{ $isActive ? 'linear-gradient(135deg,#6a0f70,#b95cb7)' : '#fff' }};
                    border:1px solid {{ $isActive ? 'transparent' : 'rgba(185,92,183,0.22)' }};
                    text-decoration:none; white-space:nowrap;
                ">
                    {{ $label }}
                    <span style="
                        font-size:11px; font-weight:600;
                        color:{{ $isActive ? '#fff' : '#9b6aad' }};
                        background:{{ $isActive ? 'rgba(255,255,255,0.22)' : '#f5eef9' }};
                        border-radius:10px; padding:1px 7px;
                    ">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        {{-- Search — plain GET form; preserves the active status tab. --}}
        <form method="GET" action="{{ route('marketing.blog.index') }}" style="display:flex; align-items:center; gap:8px;">
            @if ($activeStatus !== 'all')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
            <div style="display:flex; align-items:center; gap:7px; background:#fff; border:1px solid rgba(185,92,183,0.2); border-radius:7px; padding:0 10px; height:36px; width:220px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search title or slug…" style="border:none; background:transparent; outline:none; font-family:'Inter', sans-serif; font-size:13px; color:#1e0a2c; width:100%;" aria-label="Search posts">
            </div>
            <button type="submit" style="height:36px; padding:0 14px; font-family:'Inter', sans-serif; font-size:12.5px; font-weight:500; color:#fff; background:#6a0f70; border:none; border-radius:7px; cursor:pointer;">Search</button>
            @if ($search !== '' || $activeStatus !== 'all')
                <a href="{{ route('marketing.blog.index') }}" style="font-family:'Inter', sans-serif; font-size:12.5px; color:#9b6aad; text-decoration:none;">Clear</a>
            @endif
        </form>
    </div>

    @if ($posts->isEmpty())

        @if ($search !== '' || $activeStatus !== 'all')
            {{-- Empty because of an active filter/search, not because the
                 clinic truly has no posts — don't offer the "write your
                 first post" CTA here, it would be misleading. --}}
            <x-marketing.empty-state
                icon='<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
                heading="No posts match"
                message="Try a different search term or status filter."
            />
        @else
            <x-marketing.empty-state
                icon='<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
                heading="No posts yet — write your first"
                message="Blog posts you write live here — draft, schedule and publish whenever a website is connected."
                cta_text="New post"
                :cta_url="route('marketing.blog.create')"
            />
        @endif

    @else

    <div style="background:#fff; border:1px solid rgba(185,92,183,0.14); border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(106,15,112,0.06);">
        <table style="width:100%; border-collapse:collapse; font-family:'Inter', sans-serif; font-size:13px;">
            <thead>
                <tr style="background:#faf5fb; border-bottom:1px solid rgba(185,92,183,0.12);">
                    <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Title</th>
                    <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Status</th>
                    <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Category</th>
                    <th style="padding:10px 14px; text-align:center; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Tags</th>
                    <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Updated</th>
                    <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Scheduled / Published</th>
                    <th style="padding:10px 14px; text-align:right; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#9b6aad;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                <tr style="border-bottom:1px solid rgba(185,92,183,0.08);" data-post-row="{{ $post->uuid }}">
                    <td style="padding:10px 14px; max-width:320px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:30px; height:30px; border-radius:5px; flex-shrink:0; overflow:hidden; background:#f3e9f4; display:flex; align-items:center; justify-content:center;">
                                @if ($post->featuredAsset)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($post->featuredAsset->file_path) }}" alt="" style="width:100%; height:100%; object-fit:cover; display:block;">
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b39cbd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                @endif
                            </div>
                            <div style="min-width:0;">
                                <a href="{{ route('marketing.blog.edit', ['blog' => $post->uuid]) }}" style="font-weight:600; color:#1e0a2c; text-decoration:none; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" onmouseover="this.style.color='#6a0f70'" onmouseout="this.style.color='#1e0a2c'">{{ $post->title ?: '(untitled)' }}</a>
                                <span style="font-size:11.5px; color:#a99bb4;">/blog/{{ $post->slug }}</span>
                            </div>
                        </div>
                    </td>
                    <td style="padding:10px 14px;">
                        <x-marketing.status-badge :status="$post->status" />
                        @php
                            // Website publishing ledger (Slice 6) — surface the
                            // most relevant per-site state as a compact chip.
                            $pubs = $post->publications;
                            $sitePub = $pubs->firstWhere('status', 'failed')
                                ?? $pubs->firstWhere('status', 'published')
                                ?? $pubs->whereIn('status', ['pending', 'publishing'])->first();
                        @endphp
                        @if ($sitePub)
                            @php
                                $chip = [
                                    'published'  => ['On site', '#16a34a', 'rgba(22,163,74,0.10)'],
                                    'failed'     => ['Publish failed', '#c62828', '#fdecec'],
                                    'pending'    => ['Queued', '#b8860b', '#fdf6e3'],
                                    'publishing' => ['Publishing', '#b8860b', '#fdf6e3'],
                                ][$sitePub->status] ?? null;
                            @endphp
                            @if ($chip)
                                <span title="{{ ucfirst(str_replace('_', ' ', $sitePub->target_type)) }} · {{ $sitePub->error ?: $sitePub->status }}" style="display:inline-block; margin-top:4px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:{{ $chip[1] }}; background:{{ $chip[2] }}; border-radius:9px; padding:1px 7px;">{{ $chip[0] }}</span>
                            @endif
                        @endif
                    </td>
                    <td style="padding:10px 14px; color:#5a4868;">{{ $post->category?->name ?? '—' }}</td>
                    <td style="padding:10px 14px; text-align:center; color:#5a4868;">{{ $post->tags_count }}</td>
                    <td style="padding:10px 14px; color:#5a4868; white-space:nowrap;" title="{{ optional($post->updated_at)->toDayDateTimeString() }}">{{ optional($post->updated_at)->diffForHumans() }}</td>
                    <td style="padding:10px 14px; color:#5a4868; white-space:nowrap;">
                        @if ($post->status === 'scheduled' && $post->scheduled_at)
                            Scheduled {{ $post->scheduled_at->format('d M Y, H:i') }}
                        @elseif ($post->status === 'published' && $post->published_at)
                            Published {{ $post->published_at->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding:10px 14px;">
                        {{-- Single kebab (⋮) trigger → dropdown. Every item reuses the
                             SAME data-action handlers in blog-list.js (endpoints
                             unchanged); only the trigger UI changed. --}}
                        <div class="bl-kebab-wrap" style="display:flex; justify-content:flex-end;">
                            <button type="button" class="bl-icon-btn bl-kebab-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions" title="Actions">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                            </button>
                            <div class="bl-kebab-menu" role="menu" hidden>
                                <a href="{{ route('marketing.blog.edit', ['blog' => $post->uuid]) }}" class="bl-menu-item" role="menuitem">Edit</a>
                                <button type="button" class="bl-menu-item" role="menuitem" data-action="history" data-uuid="{{ $post->uuid }}" data-title="{{ $post->title }}">History</button>
                                <button type="button" class="bl-menu-item" role="menuitem" data-action="duplicate" data-uuid="{{ $post->uuid }}">Duplicate</button>
                                @if ($post->status === 'archived')
                                    <button type="button" class="bl-menu-item" role="menuitem" data-action="unarchive" data-uuid="{{ $post->uuid }}">Unarchive</button>
                                @else
                                    <button type="button" class="bl-menu-item" role="menuitem" data-action="archive" data-uuid="{{ $post->uuid }}">Archive</button>
                                @endif
                                <button type="button" class="bl-menu-item bl-menu-danger" role="menuitem" data-action="delete" data-uuid="{{ $post->uuid }}" data-title="{{ $post->title }}">Delete</button>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($posts->hasPages())
        <div style="margin-top:16px;">{{ $posts->links() }}</div>
    @endif

    @endif

    {{-- ── Version history modal (populated by blog-list.js) ── --}}
    <div id="bl-history-modal" class="bl-modal" hidden>
        <div class="bl-modal-card">
            <div class="bl-modal-head">
                <div>
                    <strong>Version history</strong>
                    <div id="bl-history-title" style="font-size:12px; color:#9b6aad; margin-top:2px;"></div>
                </div>
                <button type="button" id="bl-history-close" class="bl-icon-btn" aria-label="Close">&times;</button>
            </div>
            <div id="bl-history-list" class="bl-history-list"></div>
            <div class="bl-modal-foot">
                Autosaves are pruned automatically (newest 20 kept). Manual, scheduled and publish snapshots are permanent until deleted here.
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
    .bl-wrap { font-family:'Inter', sans-serif; color:#1e0a2c; }
    .bl-icon-btn { border:1px solid transparent; background:transparent; cursor:pointer; color:#9b6aad; padding:6px; border-radius:5px; line-height:1; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
    .bl-icon-btn:hover { background:#f5eef9; color:#6a0f70; }
    .bl-icon-danger:hover { background:#fdecec; color:#c62828; }

    /* Row actions kebab menu. The menu is opened with fixed positioning by
       blog-list.js so the list container's overflow:hidden never clips it.
       The [hidden] rule outranks the base display so `hidden` reliably hides. */
    .bl-kebab-wrap { position:relative; }
    .bl-kebab-menu { min-width:170px; background:#fff; border:1px solid rgba(185,92,183,0.2); border-radius:10px; box-shadow:0 8px 24px rgba(60,10,60,0.16); padding:6px; z-index:50; display:flex; flex-direction:column; }
    .bl-kebab-menu[hidden] { display:none; }
    .bl-menu-item { display:block; width:100%; text-align:left; box-sizing:border-box; font-family:'Inter',sans-serif; font-size:13px; color:#1e0a2c; background:transparent; border:none; border-radius:6px; padding:8px 11px; cursor:pointer; text-decoration:none; }
    .bl-menu-item:hover, .bl-menu-item:focus { background:#f5eef9; color:#6a0f70; outline:none; }
    .bl-menu-danger { color:#c62828; }
    .bl-menu-danger:hover, .bl-menu-danger:focus { background:#fdecec; color:#c62828; }

    .bl-modal { position:fixed; inset:0; background:rgba(30,10,44,0.42); display:flex; align-items:center; justify-content:center; z-index:140; }
    .bl-modal-card { background:#fff; border-radius:8px; width:min(520px,92vw); max-height:78vh; display:flex; flex-direction:column; overflow:hidden; }
    .bl-modal-head { display:flex; align-items:flex-start; justify-content:space-between; padding:14px 16px; border-bottom:1px solid rgba(185,92,183,0.14); }
    .bl-history-list { padding:8px 10px; overflow-y:auto; flex:1; }
    .bl-history-row { display:flex; align-items:center; gap:10px; padding:9px 8px; border-bottom:1px solid rgba(185,92,183,0.08); }
    .bl-history-row:last-child { border-bottom:none; }
    .bl-history-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.03em; padding:2px 8px; border-radius:10px; flex-shrink:0; }
    .bl-history-label[data-label="autosave"] { background:#f0f0f0; color:#78716c; }
    .bl-history-label[data-label="manual"] { background:#f3e9f4; color:#6a0f70; }
    .bl-history-label[data-label="publish"] { background:rgba(22,163,74,0.10); color:#16a34a; }
    .bl-history-label[data-label="scheduled"] { background:rgba(124,58,237,0.10); color:#7c3aed; }
    .bl-history-meta { flex:1; min-width:0; font-size:12.5px; color:#3d2450; }
    .bl-history-meta small { display:block; font-size:11px; color:#9b8aa6; margin-top:1px; }
    .bl-history-actions { display:flex; align-items:center; gap:2px; flex-shrink:0; }
    .bl-history-btn { font-size:11.5px; font-weight:600; color:#6a0f70; background:#f5eef9; border:none; border-radius:4px; padding:5px 9px; cursor:pointer; }
    .bl-history-btn:hover { background:#e8d5f5; }
    .bl-modal-foot { padding:10px 16px; border-top:1px solid rgba(185,92,183,0.1); font-size:11.5px; color:#9b8aa6; line-height:1.5; }
    .bl-history-empty { padding:30px 16px; text-align:center; font-size:13px; color:#9b8aa6; }
</style>
@endpush

@push('scripts')
<script>
    window.__BLOG_LIST__ = {
        csrf: @json(csrf_token()),
        endpoints: {
            duplicate:       @json(route('marketing.blog.duplicate',       ['blog' => '__UUID__'])),
            archive:         @json(route('marketing.blog.archive',         ['blog' => '__UUID__'])),
            unarchive:       @json(route('marketing.blog.unarchive',       ['blog' => '__UUID__'])),
            destroy:         @json(route('marketing.blog.destroy',         ['blog' => '__UUID__'])),
            editPage:        @json(route('marketing.blog.edit',            ['blog' => '__UUID__'])),
            versionsIndex:   @json(route('marketing.blog.versions.index',  ['blog' => '__UUID__'])),
            versionsRestore: @json(route('marketing.blog.versions.restore',['blog' => '__UUID__', 'version' => '__VERSION__'])),
            versionsDestroy: @json(route('marketing.blog.versions.destroy',['blog' => '__UUID__', 'version' => '__VERSION__'])),
        },
    };
</script>
<script src="{{ asset('js/blog/blog-list.js') }}"></script>
@endpush
