{{--
| Marketing — Library (Phase 2.6)
| File: resources/views/marketing/library/index.blade.php
| 3-panel layout: sidebar | asset grid | detail panel
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Library'; @endphp
@section('page-title', 'Marketing — Library')

@section('marketing-content')

<style>
/* ── Library-scoped styles ────────────────────────────────────────────── */
.lib-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(185,92,183,0.25) transparent; }
.lib-scrollbar::-webkit-scrollbar { width: 5px; }
.lib-scrollbar::-webkit-scrollbar-thumb { background: rgba(185,92,183,0.3); border-radius: 3px; }

.lib-folder-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 10px; margin: 1px 6px; border-radius: 5px;
    cursor: pointer; transition: background 120ms;
}
.lib-folder-row:hover { background: rgba(106,15,112,0.06); }

.lib-asset-card {
    background: #fff;
    border-radius: 8px;
    border: 1.5px solid rgba(185,92,183,0.12);
    overflow: hidden;
    cursor: pointer;
    transition: box-shadow 160ms, border-color 160ms, transform 120ms;
    position: relative;
}
.lib-asset-card:hover {
    box-shadow: 0 4px 18px rgba(106,15,112,0.13);
    border-color: rgba(106,15,112,0.25);
    transform: translateY(-1px);
}
.lib-asset-card .card-menu-btn {
    opacity: 0;
    transition: opacity 120ms;
}
.lib-asset-card:hover .card-menu-btn { opacity: 1; }

.lib-filter-select {
    font-family: 'Inter', sans-serif; font-size: 12px; color: #5a4868;
    background: #fff; border: 1px solid rgba(185,92,183,0.2); border-radius: 5px;
    padding: 0 26px 0 10px; height: 32px; cursor: pointer; outline: none;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239b6aad' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center;
}
.lib-filter-select:focus { border-color: #6a0f70; }

.lib-detail-row {
    display: flex; flex-direction: column; gap: 2px;
    padding: 8px 0; border-bottom: 1px solid rgba(185,92,183,0.08);
}
.lib-detail-row:last-child { border-bottom: none; }
.lib-detail-label {
    font-family: 'Inter', sans-serif; font-size: 10.5px; font-weight: 500;
    color: #9b6aad; text-transform: uppercase; letter-spacing: 0.05em;
}
.lib-detail-value {
    font-family: 'Inter', sans-serif; font-size: 12.5px; color: #1e0a2c;
    word-break: break-all;
}

.lib-tag-chip {
    display: inline-flex; align-items: center;
    background: rgba(185,92,183,0.1); color: #6a0f70;
    font-family: 'Inter', sans-serif; font-size: 10.5px; font-weight: 500;
    padding: 2px 8px; border-radius: 20px;
    border: 1px solid rgba(185,92,183,0.2);
}

.lib-type-badge {
    font-family: 'Inter', sans-serif; font-size: 9.5px; font-weight: 700;
    letter-spacing: 0.06em; text-transform: uppercase;
    padding: 2px 7px; border-radius: 3px;
}

.lib-more-dropdown {
    position: absolute; bottom: calc(100% + 6px); left: 0; right: 0;
    background: #fff; border: 1px solid rgba(185,92,183,0.18);
    border-radius: 7px; box-shadow: 0 8px 24px rgba(30,10,44,0.12);
    overflow: hidden; z-index: 50;
}
.lib-more-dropdown button {
    display: block; width: 100%; text-align: left;
    padding: 9px 14px; background: none; border: none;
    font-family: 'Inter', sans-serif; font-size: 12.5px; color: #1e0a2c;
    cursor: pointer; transition: background 100ms;
}
.lib-more-dropdown button:hover { background: rgba(106,15,112,0.06); }
.lib-more-dropdown button.danger { color: #dc2626; }
.lib-more-dropdown button.danger:hover { background: rgba(220,38,38,0.06); }
</style>

{{-- ═══════════════════════════════════════════════════════════════════════
     LIBRARY APP ROOT (Alpine)
═══════════════════════════════════════════════════════════════════════ --}}
<div
    x-data="libraryApp()"
    style="
        display: flex;
        margin: 0 -32px -32px;
        height: calc(100vh - 145px);
        overflow: hidden;
        background: #f5f0f7;
    "
>

{{-- ══════════════════════════════════════════════════════════════════════
     LEFT PANEL — Sidebar (~220px)
══════════════════════════════════════════════════════════════════════ --}}
<aside style="
    width: 220px; flex-shrink: 0;
    background: #fff;
    border-right: 1px solid rgba(185,92,183,0.13);
    display: flex; flex-direction: column;
    overflow: hidden;
">

    {{-- Tab strip: My Library | DAM Assets --}}
    <div style="
        display: flex; flex-shrink: 0;
        border-bottom: 1px solid rgba(185,92,183,0.12);
    ">
        {{-- My Library tab --}}
        <button
            @click="activeTab = 'my-library'"
            :style="activeTab === 'my-library'
                ? 'border-bottom: 2px solid #6a0f70; color: #6a0f70; font-weight: 600;'
                : 'border-bottom: 2px solid transparent; color: #7a6884; font-weight: 400;'"
            style="
                flex: 1; padding: 11px 4px 10px;
                background: none; border-left: none; border-right: none; border-top: none;
                font-family: 'Inter', sans-serif; font-size: 12px;
                cursor: pointer; transition: all 140ms; white-space: nowrap;
            "
        >My Library</button>

        {{-- DAM Assets tab --}}
        <button
            @click="activeTab = 'dam'"
            :style="activeTab === 'dam'
                ? 'border-bottom: 2px solid #6a0f70; color: #6a0f70; font-weight: 600;'
                : 'border-bottom: 2px solid transparent; color: #7a6884; font-weight: 400;'"
            style="
                flex: 1; padding: 11px 4px 10px;
                background: none; border-left: none; border-right: none; border-top: none;
                font-family: 'Inter', sans-serif; font-size: 11.5px;
                cursor: pointer; transition: all 140ms; white-space: nowrap;
                display: flex; align-items: center; justify-content: center; gap: 5px;
            "
        >
            DAM Assets
            <span style="
                background: #10b981; color: #fff;
                font-size: 8.5px; font-weight: 700;
                padding: 1px 5px; border-radius: 9px;
                letter-spacing: 0.04em; flex-shrink: 0;
            ">Connected</span>
        </button>
    </div>

    {{-- ── MY LIBRARY PANEL ───────────────────────────────────────────── --}}
    <div x-show="activeTab === 'my-library'" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">

        {{-- Search --}}
        <div style="padding: 10px 12px; border-bottom: 1px solid rgba(185,92,183,0.08); flex-shrink: 0;">
            <div style="
                display: flex; align-items: center; gap: 7px;
                background: #f9f3fa; border: 1px solid rgba(185,92,183,0.18);
                border-radius: 5px; padding: 0 9px; height: 30px;
            ">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="search"
                    placeholder="Search in my library..."
                    style="
                        border: none; background: transparent; outline: none;
                        font-family: 'Inter', sans-serif; font-size: 11.5px;
                        color: #1e0a2c; width: 100%;
                    "
                />
            </div>
        </div>

        {{-- Folder tree --}}
        <div class="lib-scrollbar" style="flex: 1; overflow-y: auto; padding: 6px 0;">

            {{-- All Assets --}}
            <div
                class="lib-folder-row"
                @click="activeFolder = 'all'"
                :style="activeFolder === 'all' ? 'background:rgba(106,15,112,0.08);' : ''"
            >
                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                        :stroke="activeFolder === 'all' ? '#6a0f70' : '#9b6aad'"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span :style="activeFolder === 'all' ? 'color:#6a0f70;font-weight:600;' : 'color:#1e0a2c;'"
                        style="font-family:'Inter',sans-serif;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        All Assets
                    </span>
                </div>
                <span style="
                    font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;
                    color:#9b6aad;background:rgba(185,92,183,0.1);
                    padding:1px 6px;border-radius:9px;flex-shrink:0;
                ">1,248</span>
            </div>

            {{-- Uncategorized --}}
            <div
                class="lib-folder-row"
                @click="activeFolder = 'uncategorized'"
                :style="activeFolder === 'uncategorized' ? 'background:rgba(106,15,112,0.08);' : ''"
            >
                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                        :stroke="activeFolder === 'uncategorized' ? '#6a0f70' : '#9b6aad'"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                    </svg>
                    <span :style="activeFolder === 'uncategorized' ? 'color:#6a0f70;font-weight:600;' : 'color:#1e0a2c;'"
                        style="font-family:'Inter',sans-serif;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;">
                        Uncategorized
                    </span>
                </div>
                <span style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:#9b6aad;background:rgba(185,92,183,0.1);padding:1px 6px;border-radius:9px;flex-shrink:0;">86</span>
            </div>

            {{-- Campaigns (expandable) --}}
            <div>
                {{-- Parent row --}}
                <div
                    class="lib-folder-row"
                    @click="toggleExpanded('campaigns')"
                    :style="activeFolder === 'campaigns' ? 'background:rgba(106,15,112,0.08);' : ''"
                >
                    <div style="display:flex;align-items:center;gap:6px;min-width:0;">
                        {{-- Chevron --}}
                        <svg
                            :style="expandedFolders.includes('campaigns') ? 'transform:rotate(90deg)' : ''"
                            style="flex-shrink:0;transition:transform 150ms;"
                            width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                            :stroke="expandedFolders.includes('campaigns') ? '#6a0f70' : '#9b6aad'"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                        </svg>
                        <span :style="expandedFolders.includes('campaigns') ? 'color:#6a0f70;font-weight:600;' : 'color:#1e0a2c;'"
                            style="font-family:'Inter',sans-serif;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;">
                            Campaigns
                        </span>
                    </div>
                    <span style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:#9b6aad;background:rgba(185,92,183,0.1);padding:1px 6px;border-radius:9px;flex-shrink:0;">404</span>
                </div>

                {{-- Campaign sub-folders --}}
                <div x-show="expandedFolders.includes('campaigns')" style="padding-left: 22px;">
                    @foreach([
                        ['id'=>'smile-makeover-june',  'name'=>'Smile Makeover June',  'count'=>86],
                        ['id'=>'implant-awareness',    'name'=>'Implant Awareness',    'count'=>72],
                        ['id'=>'teeth-whitening',      'name'=>'Teeth Whitening',      'count'=>64],
                        ['id'=>'patient-testimonials', 'name'=>'Patient Testimonials', 'count'=>58],
                        ['id'=>'clinic-branding',      'name'=>'Clinic Branding',      'count'=>42],
                        ['id'=>'festivals-events',     'name'=>'Festivals & Events',   'count'=>38],
                        ['id'=>'staff-team',           'name'=>'Staff & Team',         'count'=>26],
                        ['id'=>'educational-content',  'name'=>'Educational Content',  'count'=>18],
                    ] as $sf)
                    <div
                        class="lib-folder-row"
                        @click="activeFolder = '{{ $sf['id'] }}'"
                        :style="activeFolder === '{{ $sf['id'] }}' ? 'background:rgba(106,15,112,0.08);' : ''"
                    >
                        <div style="display:flex;align-items:center;gap:7px;min-width:0;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                :stroke="activeFolder === '{{ $sf['id'] }}' ? '#6a0f70' : '#c4a8d4'"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                            </svg>
                            <span :style="activeFolder === '{{ $sf['id'] }}' ? 'color:#6a0f70;font-weight:600;' : 'color:#5a4868;'"
                                style="font-family:'Inter',sans-serif;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $sf['name'] }}
                            </span>
                        </div>
                        <span style="font-family:'Inter',sans-serif;font-size:10px;color:#b99cc8;flex-shrink:0;">{{ $sf['count'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Recycle Bin --}}
            <div style="margin-top: 4px;">
                <div
                    class="lib-folder-row"
                    @click="activeFolder = 'recycle-bin'"
                    :style="activeFolder === 'recycle-bin' ? 'background:rgba(106,15,112,0.08);' : ''"
                >
                    <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                            :stroke="activeFolder === 'recycle-bin' ? '#6a0f70' : '#9b6aad'"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                            <path d="M10 11v6"/><path d="M14 11v6"/>
                            <path d="M9 6V4h6v2"/>
                        </svg>
                        <span :style="activeFolder === 'recycle-bin' ? 'color:#6a0f70;font-weight:600;' : 'color:#1e0a2c;'"
                            style="font-family:'Inter',sans-serif;font-size:12.5px;">
                            Recycle Bin
                        </span>
                    </div>
                    <span style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:#9b6aad;background:rgba(185,92,183,0.1);padding:1px 6px;border-radius:9px;flex-shrink:0;">12</span>
                </div>
            </div>

        </div>{{-- /folder tree --}}

        {{-- Storage Usage --}}
        <div style="
            border-top: 1px solid rgba(185,92,183,0.12);
            padding: 12px 14px; flex-shrink: 0;
        ">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:500;color:#5a4868;">Storage Usage</span>
                <span style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#6a0f70;">34%</span>
            </div>
            <div style="
                height: 5px; background: rgba(185,92,183,0.15); border-radius: 3px; overflow: hidden;
            ">
                <div style="width:34%;height:100%;background:linear-gradient(90deg,#6a0f70,#b95cb7);border-radius:3px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <span style="font-family:'Inter',sans-serif;font-size:10.5px;color:#9b6aad;">68.4 GB of 200 GB</span>
                <a href="#" style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:#6a0f70;text-decoration:none;">Manage Storage</a>
            </div>
        </div>

    </div>{{-- /my-library panel --}}

    {{-- ── DAM PANEL (sidebar) ────────────────────────────────────────── --}}
    <div x-show="activeTab === 'dam'" style="flex:1;overflow-y:auto;padding:14px 12px;" class="lib-scrollbar">
        <div style="
            background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25);
            border-radius: 7px; padding: 10px 12px; margin-bottom: 14px;
        ">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <div style="width:7px;height:7px;border-radius:50%;background:#10b981;flex-shrink:0;"></div>
                <span style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:600;color:#065f46;">Connected to Brandfolder</span>
            </div>
            <p style="font-family:'Inter',sans-serif;font-size:11px;color:#047857;margin:0;">
                4 assets synced · Last sync 2 hours ago
            </p>
        </div>
        <p style="font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;line-height:1.6;margin:0;">
            Browse and use assets directly from your connected DAM. Assets cannot be edited here.
        </p>
    </div>

</aside>


{{-- ══════════════════════════════════════════════════════════════════════
     MAIN AREA — Asset grid
══════════════════════════════════════════════════════════════════════ --}}
<main style="
    flex: 1; display: flex; flex-direction: column;
    overflow: hidden; min-width: 0;
">

    {{-- Filter bar (sticky) --}}
    <div style="
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 10px 18px; background: #fff;
        border-bottom: 1px solid rgba(185,92,183,0.12);
        flex-shrink: 0;
    ">
        <select class="lib-filter-select">
            <option>Asset Type</option>
            <option>Image</option>
            <option>Video</option>
            <option>Carousel</option>
        </select>
        <select class="lib-filter-select">
            <option>Platform</option>
            <option>Instagram</option>
            <option>Facebook</option>
            <option>WhatsApp</option>
        </select>
        <select class="lib-filter-select">
            <option>Campaign</option>
            <option>Smile Makeover June</option>
            <option>Implant Awareness</option>
            <option>Teeth Whitening</option>
        </select>
        <select class="lib-filter-select">
            <option>Tags</option>
            <option>before-after</option>
            <option>testimonial</option>
            <option>education</option>
        </select>
        <select class="lib-filter-select">
            <option>Date Modified</option>
            <option>Today</option>
            <option>This week</option>
            <option>This month</option>
        </select>
        <button style="
            font-family:'Inter',sans-serif;font-size:12px;color:#9b6aad;
            background:none;border:none;cursor:pointer;padding:0 4px;
            text-decoration:underline;text-underline-offset:2px;
        ">Clear all</button>

        {{-- Spacer + View toggle --}}
        <div style="margin-left:auto;display:flex;align-items:center;gap:4px;">
            {{-- Grid view --}}
            <button
                @click="viewMode = 'grid'"
                :style="viewMode === 'grid' ? 'background:rgba(106,15,112,0.1);color:#6a0f70;border-color:rgba(106,15,112,0.25);' : 'background:#fff;color:#9b6aad;'"
                style="
                    width:30px;height:30px;display:flex;align-items:center;justify-content:center;
                    border:1px solid rgba(185,92,183,0.2);border-radius:5px;cursor:pointer;transition:all 120ms;
                "
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </button>
            {{-- List view --}}
            <button
                @click="viewMode = 'list'"
                :style="viewMode === 'list' ? 'background:rgba(106,15,112,0.1);color:#6a0f70;border-color:rgba(106,15,112,0.25);' : 'background:#fff;color:#9b6aad;'"
                style="
                    width:30px;height:30px;display:flex;align-items:center;justify-content:center;
                    border:1px solid rgba(185,92,183,0.2);border-radius:5px;cursor:pointer;transition:all 120ms;
                "
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Scrollable grid area --}}
    <div class="lib-scrollbar" style="flex:1;overflow-y:auto;">

        {{-- ── MY LIBRARY GRID ──────────────────────────────────────────── --}}
        <div x-show="activeTab === 'my-library'">

            {{-- Folder header --}}
            <div style="
                display:flex;align-items:center;gap:12px;
                padding:14px 18px 10px;
                background:#fff;border-bottom:1px solid rgba(185,92,183,0.1);
            ">
                <div style="
                    width:36px;height:36px;border-radius:8px;flex-shrink:0;
                    background:linear-gradient(135deg,rgba(106,15,112,0.12),rgba(185,92,183,0.2));
                    display:flex;align-items:center;justify-content:center;
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 style="
                        font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;
                        color:#1e0a2c;margin:0;
                    ">Smile Makeover June</h2>
                    <span style="font-family:'Inter',sans-serif;font-size:12px;color:#9b6aad;">86 assets</span>
                </div>
                <button style="
                    margin-left:auto;width:30px;height:30px;
                    background:none;border:1px solid rgba(185,92,183,0.2);border-radius:6px;
                    cursor:pointer;font-size:16px;color:#9b6aad;
                    display:flex;align-items:center;justify-content:center;
                    transition:all 120ms;
                "
                onmouseover="this.style.background='rgba(106,15,112,0.07)'"
                onmouseout="this.style.background='none'"
                >···</button>
            </div>

            {{-- Asset grid (3 per row) --}}
            <div x-show="viewMode === 'grid'" style="
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 14px;
                padding: 16px;
            ">
                @foreach($assets as $asset)
                <div
                    class="lib-asset-card"
                    @click="selectAsset({{ json_encode($asset) }})"
                    :style="selectedAsset && selectedAsset.id == {{ $asset['id'] }}
                        ? 'border-color:#6a0f70;box-shadow:0 0 0 2px rgba(106,15,112,0.15);'
                        : ''"
                >
                    {{-- Thumbnail --}}
                    <div style="position:relative;height:140px;background:{{ $asset['thumb_color'] }};overflow:hidden;">

                        {{-- Type badge --}}
                        @if($asset['type'] === 'image')
                        <span class="lib-type-badge" style="
                            position:absolute;top:8px;left:8px;z-index:2;
                            background:rgba(255,255,255,0.9);color:#5a4868;
                        ">IMG</span>
                        @elseif($asset['type'] === 'video')
                        <span class="lib-type-badge" style="
                            position:absolute;top:8px;left:8px;z-index:2;
                            background:rgba(30,10,44,0.75);color:#fff;
                        ">VIDEO</span>
                        @else
                        <span class="lib-type-badge" style="
                            position:absolute;top:8px;left:8px;z-index:2;
                            background:rgba(79,172,254,0.9);color:#fff;
                        ">SLIDES</span>
                        @endif

                        {{-- Video overlays --}}
                        @if($asset['type'] === 'video')
                        <div style="
                            position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;
                        ">
                            <div style="
                                width:38px;height:38px;border-radius:50%;
                                background:rgba(255,255,255,0.92);
                                display:flex;align-items:center;justify-content:center;
                                box-shadow:0 2px 10px rgba(0,0,0,0.18);
                            ">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="#6a0f70" stroke="none">
                                    <polygon points="5 3 19 12 5 21 5 3"/>
                                </svg>
                            </div>
                        </div>
                        <span style="
                            position:absolute;bottom:8px;right:8px;z-index:2;
                            font-family:'Inter',sans-serif;font-size:10px;font-weight:600;
                            background:rgba(0,0,0,0.6);color:#fff;
                            padding:2px 6px;border-radius:3px;
                        ">{{ $asset['duration'] }}</span>
                        @endif

                        {{-- Carousel icon --}}
                        @if($asset['type'] === 'carousel')
                        <div style="
                            position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;
                        ">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="10" rx="2"/><path d="M17 7V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2"/>
                                <line x1="12" y1="12" x2="12" y2="12"/>
                            </svg>
                        </div>
                        @endif

                        {{-- Subtle grid pattern overlay --}}
                        <div style="
                            position:absolute;inset:0;
                            background:repeating-linear-gradient(
                                45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%
                            );
                            background-size:8px 8px;
                        "></div>
                    </div>

                    {{-- Card body --}}
                    <div style="padding:10px 12px 11px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;">
                            <span style="
                                font-family:'Inter',sans-serif;font-size:12px;font-weight:500;
                                color:#1e0a2c;line-height:1.35;
                                overflow:hidden;text-overflow:ellipsis;display:-webkit-box;
                                -webkit-line-clamp:2;-webkit-box-orient:vertical;
                            ">{{ $asset['filename'] }}</span>
                            <button class="card-menu-btn" style="
                                flex-shrink:0;background:none;border:none;cursor:pointer;
                                font-size:16px;color:#9b6aad;line-height:1;padding:0 2px;
                            ">···</button>
                        </div>
                        <div style="
                            font-family:'Inter',sans-serif;font-size:11px;
                            color:#9b6aad;margin-top:3px;
                        ">{{ $asset['size'] }} · {{ $asset['date'] }}</div>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:7px;">
                            @foreach(array_slice($asset['tags'], 0, 2) as $tag)
                            <span class="lib-tag-chip">{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- List view --}}
            <div x-show="viewMode === 'list'" style="padding: 8px 16px;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(185,92,183,0.12);">
                            <th style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#9b6aad;text-align:left;padding:8px 10px;text-transform:uppercase;letter-spacing:0.05em;">File</th>
                            <th style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#9b6aad;text-align:left;padding:8px 10px;text-transform:uppercase;letter-spacing:0.05em;">Type</th>
                            <th style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#9b6aad;text-align:left;padding:8px 10px;text-transform:uppercase;letter-spacing:0.05em;">Size</th>
                            <th style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#9b6aad;text-align:left;padding:8px 10px;text-transform:uppercase;letter-spacing:0.05em;">Modified</th>
                            <th style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#9b6aad;text-align:left;padding:8px 10px;text-transform:uppercase;letter-spacing:0.05em;">Campaign</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assets as $asset)
                        <tr
                            @click="selectAsset({{ json_encode($asset) }})"
                            :style="selectedAsset && selectedAsset.id == {{ $asset['id'] }} ? 'background:rgba(106,15,112,0.05);' : ''"
                            style="border-bottom:1px solid rgba(185,92,183,0.07);cursor:pointer;transition:background 100ms;"
                            onmouseover="this.style.background='rgba(106,15,112,0.04)'"
                            onmouseout="this.style.background=''"
                        >
                            <td style="padding:9px 10px;">
                                <div style="display:flex;align-items:center;gap:9px;">
                                    <div style="width:32px;height:32px;border-radius:5px;flex-shrink:0;background:{{ $asset['thumb_color'] }};"></div>
                                    <span style="font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;">{{ $asset['filename'] }}</span>
                                </div>
                            </td>
                            <td style="padding:9px 10px;">
                                <span class="lib-type-badge" style="background:rgba(185,92,183,0.1);color:#6a0f70;">{{ strtoupper($asset['type']) }}</span>
                            </td>
                            <td style="padding:9px 10px;font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;">{{ $asset['size'] }}</td>
                            <td style="padding:9px 10px;font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;">{{ $asset['date'] }}</td>
                            <td style="padding:9px 10px;font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;">{{ $asset['campaign'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>{{-- /my-library grid --}}

        {{-- ── DAM ASSET GRID ───────────────────────────────────────────── --}}
        <div x-show="activeTab === 'dam'">

            {{-- Folder header --}}
            <div style="
                display:flex;align-items:center;gap:12px;
                padding:14px 18px 10px;
                background:#fff;border-bottom:1px solid rgba(185,92,183,0.1);
            ">
                <div style="
                    width:36px;height:36px;border-radius:8px;flex-shrink:0;
                    background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(16,185,129,0.22));
                    display:flex;align-items:center;justify-content:center;
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
                    </svg>
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <h2 style="
                            font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;
                            color:#1e0a2c;margin:0;
                        ">Brandfolder Assets</h2>
                        <span style="
                            background:#10b981;color:#fff;font-size:9px;font-weight:700;
                            padding:2px 7px;border-radius:9px;letter-spacing:0.04em;
                        ">Connected</span>
                    </div>
                    <span style="font-family:'Inter',sans-serif;font-size:12px;color:#9b6aad;">4 synced assets · read-only</span>
                </div>
            </div>

            {{-- DAM grid --}}
            <div style="
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 14px;
                padding: 16px;
            ">
                @foreach($damAssets as $dam)
                <div
                    class="lib-asset-card"
                    @click="selectAsset({{ json_encode($dam) }})"
                    :style="selectedAsset && selectedAsset.id === '{{ $dam['id'] }}' ? 'border-color:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,0.15);' : ''"
                >
                    {{-- Thumbnail --}}
                    <div style="position:relative;height:140px;background:{{ $dam['thumb_color'] }};overflow:hidden;">

                        {{-- DAM origin label --}}
                        <span style="
                            position:absolute;top:8px;right:8px;z-index:3;
                            font-family:'Inter',sans-serif;font-size:9px;font-weight:700;
                            background:#10b981;color:#fff;
                            padding:2px 6px;border-radius:3px;letter-spacing:0.04em;
                        ">{{ $dam['dam_origin'] }}</span>

                        {{-- Type badge --}}
                        @if($dam['type'] === 'image')
                        <span class="lib-type-badge" style="position:absolute;top:8px;left:8px;z-index:2;background:rgba(255,255,255,0.9);color:#5a4868;">IMG</span>
                        @elseif($dam['type'] === 'video')
                        <span class="lib-type-badge" style="position:absolute;top:8px;left:8px;z-index:2;background:rgba(30,10,44,0.75);color:#fff;">VIDEO</span>
                        @else
                        <span class="lib-type-badge" style="position:absolute;top:8px;left:8px;z-index:2;background:rgba(79,172,254,0.9);color:#fff;">SLIDES</span>
                        @endif

                        @if($dam['type'] === 'video')
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;">
                            <div style="width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,0.92);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,0.18);">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="#6a0f70" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                        <span style="position:absolute;bottom:8px;right:8px;z-index:2;font-family:'Inter',sans-serif;font-size:10px;font-weight:600;background:rgba(0,0,0,0.6);color:#fff;padding:2px 6px;border-radius:3px;">{{ $dam['duration'] }}</span>
                        @endif

                        <div style="position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;"></div>
                    </div>

                    {{-- Card body --}}
                    <div style="padding:10px 12px 11px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;">
                            <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:500;color:#1e0a2c;line-height:1.35;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">{{ $dam['filename'] }}</span>
                            <button class="card-menu-btn" style="flex-shrink:0;background:none;border:none;cursor:pointer;font-size:16px;color:#9b6aad;line-height:1;padding:0 2px;">···</button>
                        </div>
                        <div style="font-family:'Inter',sans-serif;font-size:11px;color:#9b6aad;margin-top:3px;">{{ $dam['size'] }} · {{ $dam['date'] }}</div>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:7px;">
                            @foreach(array_slice($dam['tags'], 0, 2) as $tag)
                            <span class="lib-tag-chip">{{ $tag }}</span>
                            @endforeach
                            <span style="
                                display:inline-flex;align-items:center;
                                background:rgba(16,185,129,0.1);color:#059669;
                                font-family:'Inter',sans-serif;font-size:10px;font-weight:600;
                                padding:2px 7px;border-radius:20px;
                                border:1px solid rgba(16,185,129,0.2);
                            ">DAM</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

        </div>{{-- /dam grid --}}

    </div>{{-- /scrollable grid area --}}

</main>


{{-- ══════════════════════════════════════════════════════════════════════
     RIGHT DETAIL PANEL (~280px)
══════════════════════════════════════════════════════════════════════ --}}
<aside
    x-show="selectedAsset !== null"
    style="
        width: 280px; flex-shrink: 0;
        background: #fff;
        border-left: 1px solid rgba(185,92,183,0.13);
        display: flex; flex-direction: column;
        overflow: hidden;
    "
>

    {{-- Details | Activity tabs --}}
    <div style="
        display:flex;flex-shrink:0;
        border-bottom:1px solid rgba(185,92,183,0.12);
    ">
        <button
            @click="detailTab = 'details'"
            :style="detailTab === 'details'
                ? 'border-bottom:2px solid #6a0f70;color:#6a0f70;font-weight:600;'
                : 'border-bottom:2px solid transparent;color:#7a6884;'"
            style="
                flex:1;padding:11px 4px 10px;
                background:none;border-left:none;border-right:none;border-top:none;
                font-family:'Inter',sans-serif;font-size:12.5px;
                cursor:pointer;transition:all 140ms;
            "
        >Details</button>
        <button
            @click="detailTab = 'activity'"
            :style="detailTab === 'activity'
                ? 'border-bottom:2px solid #6a0f70;color:#6a0f70;font-weight:600;'
                : 'border-bottom:2px solid transparent;color:#7a6884;'"
            style="
                flex:1;padding:11px 4px 10px;
                background:none;border-left:none;border-right:none;border-top:none;
                font-family:'Inter',sans-serif;font-size:12.5px;
                cursor:pointer;transition:all 140ms;
            "
        >Activity</button>
    </div>

    {{-- Large thumbnail --}}
    <div
        x-show="selectedAsset"
        :style="selectedAsset ? 'background:' + selectedAsset.thumb_color : ''"
        style="height:180px;flex-shrink:0;position:relative;overflow:hidden;"
    >
        {{-- Play overlay for video --}}
        <template x-if="selectedAsset && selectedAsset.type === 'video'">
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.92);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,0.2);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#6a0f70" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
            </div>
        </template>
        {{-- Type badge on thumbnail --}}
        <div x-show="selectedAsset" style="position:absolute;bottom:10px;left:10px;">
            <span
                x-text="selectedAsset ? selectedAsset.type.toUpperCase() : ''"
                style="
                    font-family:'Inter',sans-serif;font-size:9.5px;font-weight:700;
                    letter-spacing:0.06em;text-transform:uppercase;
                    background:rgba(255,255,255,0.9);color:#5a4868;
                    padding:2px 7px;border-radius:3px;
                "
            ></span>
        </div>
        <div style="position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;"></div>
    </div>

    {{-- ── DETAILS TAB ──────────────────────────────────────────────── --}}
    <div x-show="detailTab === 'details'" class="lib-scrollbar" style="flex:1;overflow-y:auto;padding:0 14px;">

        {{-- File Name --}}
        <div class="lib-detail-row">
            <div class="lib-detail-label" style="display:flex;align-items:center;gap:4px;">
                File Name
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#c4a8d4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
            </div>
            <div class="lib-detail-value" x-text="selectedAsset ? selectedAsset.filename : ''"></div>
        </div>

        {{-- Type --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Type</span>
            <span
                class="lib-type-badge"
                x-text="selectedAsset ? selectedAsset.type.toUpperCase() : ''"
                style="background:rgba(185,92,183,0.1);color:#6a0f70;"
            ></span>
        </div>

        {{-- Size --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Size</span>
            <span class="lib-detail-value" x-text="selectedAsset ? selectedAsset.size : ''"></span>
        </div>

        {{-- Dimensions --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Dimensions</span>
            <span class="lib-detail-value" x-text="selectedAsset ? selectedAsset.dimensions : ''"></span>
        </div>

        {{-- Uploaded On --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Uploaded On</span>
            <span class="lib-detail-value" x-text="selectedAsset ? selectedAsset.date : ''"></span>
        </div>

        {{-- Uploaded By --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Uploaded By</span>
            <span class="lib-detail-value" x-text="selectedAsset ? selectedAsset.uploaded_by : ''"></span>
        </div>

        {{-- Folder --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Folder</span>
            <span style="
                font-family:'Inter',sans-serif;font-size:12px;color:#6a0f70;font-weight:500;
                max-width:140px;text-align:right;
            " x-text="selectedAsset ? selectedAsset.folder : ''"></span>
        </div>

        {{-- Campaign --}}
        <div class="lib-detail-row" style="flex-direction:row;justify-content:space-between;align-items:center;">
            <span class="lib-detail-label">Campaign</span>
            <span style="
                font-family:'Inter',sans-serif;font-size:12px;color:#6a0f70;font-weight:500;
                max-width:140px;text-align:right;
            " x-text="selectedAsset ? selectedAsset.campaign : ''"></span>
        </div>

        {{-- Tags --}}
        <div class="lib-detail-row">
            <span class="lib-detail-label">Tags</span>
            <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:5px;" x-show="selectedAsset">
                <template x-if="selectedAsset">
                    <template x-for="tag in selectedAsset.tags" :key="tag">
                        <span class="lib-tag-chip" x-text="tag"></span>
                    </template>
                </template>
                <button style="
                    font-family:'Inter',sans-serif;font-size:10.5px;color:#9b6aad;
                    background:rgba(185,92,183,0.07);border:1px dashed rgba(185,92,183,0.3);
                    border-radius:20px;padding:2px 8px;cursor:pointer;
                    transition:background 120ms;
                "
                onmouseover="this.style.background='rgba(185,92,183,0.14)'"
                onmouseout="this.style.background='rgba(185,92,183,0.07)'"
                >+ Add Tag</button>
            </div>
        </div>

        {{-- Description --}}
        <div class="lib-detail-row" style="border-bottom:none;padding-bottom:14px;">
            <span class="lib-detail-label">Description</span>
            <textarea
                x-model="selectedAsset ? selectedAsset.description : ''"
                style="
                    margin-top:5px;width:100%;box-sizing:border-box;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;line-height:1.5;
                    background:#f9f3fa;border:1px solid rgba(185,92,183,0.18);
                    border-radius:5px;padding:8px 10px;resize:vertical;
                    outline:none;min-height:68px;
                "
                onfocus="this.style.borderColor='#6a0f70'"
                onblur="this.style.borderColor='rgba(185,92,183,0.18)'"
                placeholder="Add a description..."
            ></textarea>
        </div>

    </div>{{-- /details tab --}}

    {{-- ── ACTIVITY TAB ──────────────────────────────────────────────── --}}
    <div x-show="detailTab === 'activity'" class="lib-scrollbar" style="flex:1;overflow-y:auto;padding:14px;">
        {{-- Timeline --}}
        @foreach([
            ['who'=>'Dr. Sharma',     'action'=>'uploaded this asset',        'time'=>'Jun 10, 2026 · 9:14 AM'],
            ['who'=>'Marketing Team', 'action'=>'added tag "before-after"',    'time'=>'Jun 10, 2026 · 11:02 AM'],
            ['who'=>'Marketing Team', 'action'=>'used in Smile Makeover post', 'time'=>'Jun 11, 2026 · 3:47 PM'],
            ['who'=>'Dr. Sharma',     'action'=>'updated description',         'time'=>'Jun 12, 2026 · 10:30 AM'],
        ] as $event)
        <div style="display:flex;gap:10px;margin-bottom:14px;">
            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6a0f70,#b95cb7);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-family:'Inter',sans-serif;font-size:10px;font-weight:700;color:#fff;">
                        {{ strtoupper(substr($event['who'], 0, 1)) }}
                    </span>
                </div>
                <div style="width:1px;flex:1;background:rgba(185,92,183,0.15);margin-top:4px;min-height:16px;"></div>
            </div>
            <div style="padding-top:2px;padding-bottom:14px;">
                <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:600;color:#1e0a2c;">{{ $event['who'] }}</span>
                <span style="font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;"> {{ $event['action'] }}</span>
                <div style="font-family:'Inter',sans-serif;font-size:10.5px;color:#b99cc8;margin-top:2px;">{{ $event['time'] }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Footer: Download + More Actions --}}
    <div style="
        border-top:1px solid rgba(185,92,183,0.12);
        padding:12px 14px;flex-shrink:0;
    ">
        {{-- Download button --}}
        <button style="
            width:100%;height:36px;
            background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 100%);
            color:#fff;border:none;border-radius:6px;cursor:pointer;
            font-family:'Inter',sans-serif;font-size:13px;font-weight:600;
            display:flex;align-items:center;justify-content:center;gap:7px;
            transition:opacity 150ms;box-shadow:0 2px 8px rgba(106,15,112,0.25);
        "
        onmouseover="this.style.opacity='0.9'"
        onmouseout="this.style.opacity='1'"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
        </button>

        {{-- More Actions dropdown --}}
        <div style="position:relative;margin-top:8px;">
            <button
                @click="showMoreActions = !showMoreActions"
                style="
                    width:100%;height:34px;
                    background:#fff;color:#5a4868;
                    border:1px solid rgba(185,92,183,0.25);border-radius:6px;cursor:pointer;
                    font-family:'Inter',sans-serif;font-size:12.5px;font-weight:500;
                    display:flex;align-items:center;justify-content:center;gap:6px;
                    transition:background 120ms;
                "
                onmouseover="this.style.background='rgba(106,15,112,0.05)'"
                onmouseout="this.style.background='#fff'"
            >
                More Actions
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div
                x-show="showMoreActions"
                @click.outside="showMoreActions = false"
                class="lib-more-dropdown"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
            >
                <button>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:7px;">
                        <path d="M12 19V5M5 12l7-7 7 7"/>
                    </svg>
                    Use in Post
                </button>
                <button>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:7px;">
                        <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                    </svg>
                    Move to Folder
                </button>
                <button class="danger">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:7px;">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    </div>

</aside>{{-- /detail panel --}}

</div>{{-- /library app root --}}


{{-- ══════════════════════════════════════════════════════════════════════
     ALPINE APP
══════════════════════════════════════════════════════════════════════ --}}
<script>
function libraryApp() {
    return {
        /* ── State ─────────────────────────────────────────────────── */
        activeTab:       'my-library',          // 'my-library' | 'dam'
        activeFolder:    'smile-makeover-june',
        expandedFolders: ['campaigns'],
        viewMode:        'grid',                // 'grid' | 'list'
        detailTab:       'details',             // 'details' | 'activity'
        showMoreActions: false,
        selectedAsset:   @json($selectedAsset),

        /* ── Methods ───────────────────────────────────────────────── */
        toggleExpanded(folderId) {
            const idx = this.expandedFolders.indexOf(folderId);
            if (idx === -1) {
                this.expandedFolders.push(folderId);
            } else {
                this.expandedFolders.splice(idx, 1);
            }
        },

        selectAsset(asset) {
            this.selectedAsset = asset;
            this.detailTab     = 'details';
            this.showMoreActions = false;
        },
    };
}
</script>

@endsection
