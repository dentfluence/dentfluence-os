@extends('layouts.app')

@section('page-title', 'Clinical Library — Education')

@section('head-extra')
<style>
    * { box-sizing: border-box; }

    /* ── Page shell ── */
    #edu-shell { background: #f8f9fb; min-height: 100vh; }

    /* ── Page header (same as index) ── */
    #edu-header {
        background: white; border-bottom: 1px solid #e5e7eb;
        padding: 16px 24px 0; flex-shrink: 0;
    }
    .edu-header-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
    .edu-title      { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.03em; }
    .edu-subtitle   { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .edu-header-actions { display: flex; align-items: center; gap: 8px; }
    .cms-btn-outline {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 14px; font-size: 12px; font-weight: 600;
        border: 1px solid #e5e7eb; background: white; color: #374151;
        border-radius: 6px; cursor: pointer; transition: all .15s;
    }
    .cms-btn-outline:hover { border-color: #6a0f70; color: #6a0f70; background: #faf5fb; }

    .cms-tabs { display: flex; gap: 0; }
    .cms-tab {
        padding: 10px 20px; font-size: 13px; font-weight: 600; color: #9ca3af;
        border-bottom: 2px solid transparent; cursor: pointer; text-decoration: none;
        transition: all .15s; white-space: nowrap;
    }
    .cms-tab:hover { color: #6a0f70; }
    .cms-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    /* ── Page body ── */
    #edu-body { padding: 22px 24px; }

    /* ── Category carousel ── */
    #cat-section { margin-bottom: 28px; }
    .cat-section-head { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 14px; letter-spacing: -.02em; }

    .cat-carousel { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory; }
    .cat-carousel::-webkit-scrollbar { height: 3px; }
    .cat-carousel::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 99px; }
    .cat-carousel::-webkit-scrollbar-thumb { background: #e9d5ff; border-radius: 99px; }

    .cat-card {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border: 1.5px solid #e5e7eb; border-radius: 10px;
        background: white; cursor: pointer; transition: all .15s;
        text-decoration: none; white-space: nowrap; flex-shrink: 0;
        scroll-snap-align: start; min-width: 140px;
    }
    .cat-card:hover { border-color: #b95cb7; background: #faf5fb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(106,15,112,.08); }
    .cat-card.active { border-color: #6a0f70; background: #faf5fb; box-shadow: 0 0 0 3px rgba(106,15,112,.1); }
    .cat-card.active .cat-name { color: #6a0f70; }

    .cat-icon {
        width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0; transition: all .15s;
    }
    .cat-info { flex: 1; min-width: 0; }
    .cat-name { font-size: 12px; font-weight: 700; color: #374151; transition: color .15s; }
    .cat-count { font-size: 10px; color: #9ca3af; margin-top: 1px; }

    /* ── Treatment grid ── */
    .tx-section-head { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 16px; letter-spacing: -.02em; }

    .tx-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
    @media (max-width: 1200px) { .tx-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 800px)  { .tx-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 500px)  { .tx-grid { grid-template-columns: 1fr; } }

    .tx-card {
        background: white; border: 1px solid #e5e7eb; border-radius: 12px;
        overflow: hidden; transition: all .2s; cursor: pointer;
    }
    .tx-card:hover {
        border-color: #b95cb7;
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(106,15,112,.12);
    }

    /* Thumbnail */
    .tx-thumb {
        position: relative; aspect-ratio: 16/9; overflow: hidden;
        background: linear-gradient(135deg, #1c1c2e, #2d1b4e);
    }
    .tx-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s; }
    .tx-card:hover .tx-thumb img { transform: scale(1.04); }

    /* Media type badge overlay */
    .media-badge {
        position: absolute; top: 8px; left: 8px;
        display: flex; align-items: center; gap: 4px;
        padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700;
        backdrop-filter: blur(4px);
    }
    .media-badge.video   { background: rgba(220,38,38,.85);  color: white; }
    .media-badge.photos  { background: rgba(22,163,74,.85);   color: white; }
    .media-badge.xray    { background: rgba(37,99,235,.85);   color: white; }
    .media-badge.scan    { background: rgba(124,58,237,.85);  color: white; }
    .media-badge.pdf     { background: rgba(217,119,6,.85);   color: white; }

    /* Play button overlay for videos */
    .play-overlay {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: rgba(0,0,0,.2); transition: background .15s;
    }
    .tx-card:hover .play-overlay { background: rgba(0,0,0,.35); }
    .play-btn {
        width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,.9);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 16px rgba(0,0,0,.3); transition: transform .15s;
    }
    .tx-card:hover .play-btn { transform: scale(1.1); }

    /* Duration badge */
    .duration-badge {
        position: absolute; bottom: 8px; right: 8px;
        background: rgba(0,0,0,.75); color: white;
        font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;
    }

    /* Media count overlay (for photo grids) */
    .media-count-badge {
        position: absolute; bottom: 8px; right: 8px;
        background: rgba(0,0,0,.65); color: white;
        display: flex; align-items: center; gap: 3px;
        font-size: 10px; font-weight: 600; padding: 3px 7px; border-radius: 4px;
    }

    /* Before/After split overlay */
    .ba-overlay {
        position: absolute; inset: 0; display: flex;
    }
    .ba-half {
        flex: 1; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 10px;
    }
    .ba-label {
        font-size: 11px; font-weight: 700; color: white;
        text-shadow: 0 1px 3px rgba(0,0,0,.8);
    }
    .ba-divider { width: 2px; background: white; opacity: .7; }

    /* Card body */
    .tx-body { padding: 14px 14px 12px; }
    .tx-title { font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 5px; letter-spacing: -.01em; }
    .tx-desc  { font-size: 11px; color: #6b7280; line-height: 1.5; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    .tx-stats { display: flex; align-items: center; gap: 0; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    .tx-stat { flex: 1; text-align: center; }
    .tx-stat-num { font-size: 15px; font-weight: 800; color: #111827; }
    .tx-stat-label { font-size: 9px; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-top: 1px; }
    .tx-stat-divider { width: 1px; height: 28px; background: #f3f4f6; flex-shrink: 0; }

    .tx-view-btn {
        display: flex; align-items: center; justify-content: center;
        padding: 6px 12px; border: 1.5px solid #6a0f70; border-radius: 5px;
        font-size: 11px; font-weight: 700; color: #6a0f70;
        text-decoration: none; transition: all .15s; margin-left: 10px; white-space: nowrap;
    }
    .tx-view-btn:hover { background: #6a0f70; color: white; }

    /* Empty state */
    .edu-empty { text-align: center; padding: 56px 24px; }
    .edu-empty-icon { width: 64px; height: 64px; margin: 0 auto 16px; background: #f5f3ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    /* Pagination */
    .edu-pagination { display: flex; justify-content: center; gap: 4px; margin-top: 28px; }
    .edu-pag-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e5e7eb; background: white; color: #374151; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .12s; text-decoration: none; }
    .edu-pag-btn:hover { border-color: #6a0f70; color: #6a0f70; }
    .edu-pag-btn.active { background: #6a0f70; border-color: #6a0f70; color: white; }
    .edu-pag-btn.disabled { opacity: .35; pointer-events: none; }

    /* Disclaimer footer */
    .edu-disclaimer { margin-top: 24px; padding: 10px 16px; border-top: 1px solid #e5e7eb; display: flex; align-items: flex-start; gap: 8px; font-size: 11px; color: #9ca3af; }
</style>
@endsection

@section('content')
<div id="edu-shell">

    {{-- ══ PAGE HEADER ══ --}}
    <div id="edu-header">
        <div class="edu-header-top">
            <div>
                <div class="edu-title">Clinical Library</div>
                <div class="edu-subtitle">Explore educational content and treatment resources</div>
            </div>
            <div class="edu-header-actions">
                <button class="cms-btn-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                    Watermark Settings
                </button>
                <button class="cms-btn-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                </button>
            </div>
        </div>
        <div class="cms-tabs">
            <a href="{{ route('cms.index') }}" class="cms-tab">Patient Clinical Data</a>
            <a href="{{ route('cms.education') }}" class="cms-tab active">Generic Education Library</a>
<a href="{{ route('cms.education.manage') }}" class="cms-tab">Manage Content</a>
        </div>
    </div>

    {{-- ══ BODY ══ --}}
    <div id="edu-body">

        {{-- ── Category carousel ── --}}
        <div id="cat-section">
            <div class="cat-section-head">Browse by Category</div>

            <div class="cat-carousel">
                {{-- All categories --}}
                <a href="{{ route('cms.education') }}"
                   class="cat-card {{ !$categorySlug ? 'active' : '' }}">
                    <div class="cat-icon" style="background:#f5f3ff;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </div>
                    <div class="cat-info">
                        <div class="cat-name">All Categories</div>
                        <div class="cat-count">{{ $categories->sum('active_treatments_count') }} Treatments</div>
                    </div>
                </a>

                {{-- Dynamic categories --}}
                @foreach($categories as $cat)
                @php
                $catIconSvg = match($cat->slug) {
                    'restorative'  => '<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><path d="M8 12h8M12 8v8"/>',
                    'implantology' => '<path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="4"/>',
                    'endodontics'  => '<path d="M12 2C8 2 5 5 5 9c0 3 2 5.5 4 7l3 4 3-4c2-1.5 4-4 4-7 0-4-3-7-7-7z"/>',
                    'periodontics' => '<path d="M12 22V12m0 0C8 12 4 9 4 6a8 8 0 0 1 16 0c0 3-4 6-8 6z"/>',
                    'orthodontics' => '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
                    'oral-surgery' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>',
                    'preventive'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                    'cosmetic'     => '<circle cx="12" cy="12" r="5"/><path d="M12 2v4M12 18v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83"/>',
                    default        => '<circle cx="12" cy="12" r="10"/>',
                };
                @endphp
                <a href="{{ route('cms.education', ['category' => $cat->slug]) }}"
                   class="cat-card {{ $categorySlug === $cat->slug ? 'active' : '' }}">
                    <div class="cat-icon" style="background:{{ $cat->color ?? '#6a0f70' }}18;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $cat->color ?? '#6a0f70' }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $catIconSvg !!}</svg>
                    </div>
                    <div class="cat-info">
                        <div class="cat-name">{{ $cat->name }}</div>
                        <div class="cat-count">{{ $cat->active_treatments_count }} Treatments</div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        {{-- ── Treatment grid ── --}}
        <div>
            <div class="tx-section-head">
                {{ $categorySlug ? ($categories->firstWhere('slug', $categorySlug)?->name ?? 'Treatments') : 'All Treatments' }}
            </div>

            @if($treatments->isEmpty())
            <div class="edu-empty">
                <div class="edu-empty-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                </div>
                <div style="font-size:15px;font-weight:700;color:#374151;margin-bottom:5px;">No content in this category</div>
                <div style="font-size:12px;color:#9ca3af;">Educational content for this category hasn't been added yet.</div>
                <a href="{{ route('cms.education') }}" style="display:inline-block;margin-top:12px;padding:8px 20px;background:#6a0f70;color:white;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">View All Categories</a>
            </div>
            @else
            <div class="tx-grid">
                @foreach($treatments as $treatment)
                @php
                $photoCount = $treatment->photo_count;
                $xrayCount  = $treatment->xray_count;
                $videoCount = $treatment->video_count;
                $totalMedia = $photoCount + $xrayCount + $videoCount;
                $primaryType = $treatment->primary_media_type ?? 'photos';
                @endphp
                <div class="tx-card">
                    {{-- Thumbnail --}}
                    <div class="tx-thumb">
                        <img src="{{ $treatment->thumbnail_url }}" alt="{{ $treatment->title }}" loading="lazy"
                             onerror="this.style.display='none'">

                        {{-- Media type badge --}}
                        @if($primaryType === 'video')
                        <div class="media-badge video">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Video
                        </div>
                        <div class="play-overlay">
                            <div class="play-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#6a0f70"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                        @elseif($primaryType === 'xray')
                        <div class="media-badge xray">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/></svg>
                            X-Ray
                        </div>
                        @else
                        <div class="media-badge photos">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            Photos
                        </div>
                        @endif

                        {{-- Before/After label for certain categories --}}
                        @if($photoCount >= 2 && in_array($treatment->category?->slug, ['cosmetic','restorative','orthodontics']))
                        <div class="ba-overlay">
                            <div class="ba-half"><span class="ba-label">Before</span></div>
                            <div class="ba-divider"></div>
                            <div class="ba-half"><span class="ba-label">After</span></div>
                        </div>
                        @endif

                        {{-- Count / duration overlay --}}
                        @if($primaryType === 'video' && $treatment->media->where('media_type','video')->first()?->duration_seconds)
                        <div class="duration-badge">{{ $treatment->media->where('media_type','video')->first()->formatted_duration }}</div>
                        @elseif($totalMedia > 0)
                        <div class="media-count-badge">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            {{ $totalMedia }}
                        </div>
                        @endif
                    </div>

                    {{-- Card body --}}
                    <div class="tx-body">
                        <div class="tx-title">{{ $treatment->title }}</div>
                        <div class="tx-desc">{{ $treatment->description }}</div>

                        {{-- Stats row --}}
                        <div class="tx-stats">
                            <div class="tx-stat">
                                <div class="tx-stat-num">{{ $photoCount }}</div>
                                <div class="tx-stat-label">Photos</div>
                            </div>
                            <div class="tx-stat-divider"></div>
                            <div class="tx-stat">
                                <div class="tx-stat-num">{{ $xrayCount }}</div>
                                <div class="tx-stat-label">X-Rays</div>
                            </div>
                            <div class="tx-stat-divider"></div>
                            <div class="tx-stat">
                                <div class="tx-stat-num">{{ $videoCount }}</div>
                                <div class="tx-stat-label">Videos</div>
                            </div>
                            <a href="#" class="tx-view-btn">View</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($treatments->hasPages())
            <div class="edu-pagination">
                @if($treatments->onFirstPage())
                <span class="edu-pag-btn disabled">‹</span>
                @else
                <a href="{{ $treatments->previousPageUrl() }}" class="edu-pag-btn">‹</a>
                @endif

                @foreach($treatments->getUrlRange(max(1,$treatments->currentPage()-2), min($treatments->lastPage(),$treatments->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}" class="edu-pag-btn {{ $page == $treatments->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($treatments->hasMorePages())
                <a href="{{ $treatments->nextPageUrl() }}" class="edu-pag-btn">›</a>
                @else
                <span class="edu-pag-btn disabled">›</span>
                @endif
            </div>
            @endif
            @endif
        </div>

        {{-- Disclaimer --}}
        <div class="edu-disclaimer">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                This library contains educational and reference content for clinical learning and patient education.<br>
                All content is generic and not linked to individual patients.
            </div>
        </div>

    </div>{{-- /edu-body --}}
</div>{{-- /edu-shell --}}
@endsection
