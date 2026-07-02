@extends('layouts.app')

@section('page-title', 'Education Library — Manage Content')

@section('head-extra')
<style>
    * { box-sizing: border-box; }
    [x-cloak] { display: none !important; }

    #edu-manage { background: #f8f9fb; min-height: 100vh; }

    /* ── Header ── */
    #edu-manage-header { background: white; border-bottom: 1px solid #e5e7eb; padding: 16px 24px 0; }
    .emh-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
    .emh-title { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.03em; }
    .emh-sub   { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .cms-tabs  { display: flex; }
    .cms-tab   { padding: 10px 20px; font-size: 13px; font-weight: 600; color: #9ca3af; border-bottom: 2px solid transparent; text-decoration: none; transition: all .15s; white-space: nowrap; }
    .cms-tab:hover  { color: #6a0f70; }
    .cms-tab.active { color: #6a0f70; border-bottom-color: #6a0f70; }

    /* ── 3-column layout ── */
    #edu-manage-body { display: grid; grid-template-columns: 220px 260px 1fr; min-height: calc(100vh - 110px); }

    .em-col { border-right: 1px solid #e5e7eb; background: white; display: flex; flex-direction: column; overflow: hidden; }
    .em-col:last-child { border-right: none; background: #f8f9fb; }
    .em-col-head { padding: 14px 16px 10px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
    .em-col-title { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; }
    .em-col-body  { flex: 1; overflow-y: auto; }

    /* ── Category items ── */
    .cat-item { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #f9fafb; transition: background .1s; }
    .cat-item:hover { background: #faf5fb; }
    .cat-item.active { background: #f5f3ff; border-left: 3px solid #6a0f70; }
    .cat-item-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .cat-item-name { font-size: 13px; font-weight: 600; color: #374151; flex: 1; text-decoration: none; display: block; }
    .cat-item-count { font-size: 10px; color: #9ca3af; font-weight: 600; }
    .item-edit-btn { background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 13px; padding: 2px 4px; border-radius: 3px; transition: all .12s; line-height: 1; }
    .item-edit-btn:hover { background: #f3f4f6; color: #6a0f70; }
    .item-del-btn  { background: none; border: none; color: #d1d5db; cursor: pointer; font-size: 15px; padding: 2px 4px; border-radius: 3px; transition: color .12s; line-height: 1; }
    .item-del-btn:hover { color: #dc2626; }

    /* ── Treatment items ── */
    .tx-item { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #f9fafb; transition: background .1s; }
    .tx-item:hover { background: #faf5fb; }
    .tx-item.active { background: #f5f3ff; border-left: 3px solid #6a0f70; }
    .tx-thumb-mini { width: 36px; height: 36px; border-radius: 5px; object-fit: cover; background: #f3f4f6; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .tx-item-name  { font-size: 12px; font-weight: 600; color: #374151; flex: 1; min-width: 0; text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tx-item-count { font-size: 10px; color: #9ca3af; white-space: nowrap; }

    /* ── Add button ── */
    .em-add-btn { display: flex; align-items: center; gap: 6px; width: 100%; padding: 10px 16px; background: none; border: none; border-top: 1px solid #f3f4f6; font-size: 12px; font-weight: 600; color: #6a0f70; cursor: pointer; transition: background .12s; text-align: left; }
    .em-add-btn:hover { background: #faf5fb; }

    /* ── Right panel ── */
    #right-panel { padding: 20px 24px; overflow-y: auto; height: 100%; }
    .rp-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; gap: 10px; }
    .rp-empty-icon { width: 56px; height: 56px; background: #f5f3ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    /* ── Treatment header ── */
    .tx-detail-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid #f3f4f6; }
    .tx-detail-title { font-size: 18px; font-weight: 800; color: #111827; letter-spacing: -.02em; }
    .tx-detail-cat   { font-size: 11px; color: #9ca3af; margin-top: 3px; }
    .tx-detail-desc  { font-size: 12px; color: #6b7280; margin-top: 5px; line-height: 1.6; }
    .tx-detail-actions { display: flex; gap: 6px; flex-shrink: 0; }

    /* ── Upload zone ── */
    #upload-zone { border: 2px dashed #d1d5db; border-radius: 12px; padding: 24px 20px; text-align: center; cursor: pointer; transition: all .2s; background: white; margin-bottom: 16px; }
    #upload-zone:hover, #upload-zone.drag-over { border-color: #6a0f70; background: #faf5fb; }
    .uz-icon  { width: 44px; height: 44px; border-radius: 50%; background: #f5f3ff; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; }
    .uz-title { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 3px; }
    .uz-sub   { font-size: 11px; color: #9ca3af; margin-bottom: 12px; }
    .media-type-row { display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; }
    .mt-btn { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 600; border: 1.5px solid #e5e7eb; background: white; color: #6b7280; cursor: pointer; transition: all .12s; }
    .mt-btn:hover  { border-color: #b95cb7; color: #6a0f70; }
    .mt-btn.active { background: #6a0f70; border-color: #6a0f70; color: white; }

    /* ── Upload queue ── */
    .upload-queue { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
    .uq-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; }
    .uq-preview { width: 40px; height: 40px; border-radius: 5px; background: #f3f4f6; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .uq-preview img { width: 100%; height: 100%; object-fit: cover; }
    .uq-info { flex: 1; min-width: 0; }
    .uq-name { font-size: 12px; font-weight: 600; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .uq-size { font-size: 10px; color: #9ca3af; }
    .uq-progress { height: 3px; background: #e5e7eb; border-radius: 99px; margin-top: 4px; overflow: hidden; }
    .uq-progress-fill { height: 100%; background: #6a0f70; border-radius: 99px; transition: width .3s; }
    .uq-remove { background: none; border: none; color: #d1d5db; cursor: pointer; font-size: 18px; padding: 0; transition: color .12s; }
    .uq-remove:hover { color: #dc2626; }

    /* ── Media grid ── */
    .media-section-label { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; margin-bottom: 18px; }
    .media-card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: white; transition: all .15s; position: relative; }
    .media-card:hover { border-color: #b95cb7; box-shadow: 0 4px 12px rgba(106,15,112,.1); }
    .media-thumb { aspect-ratio: 4/3; background: #f3f4f6; overflow: hidden; position: relative; }
    .media-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .media-thumb-icon { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .media-type-badge { position: absolute; top: 5px; left: 5px; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; }
    .badge-photo { background: rgba(22,163,74,.85);  color: white; }
    .badge-xray  { background: rgba(37,99,235,.85);  color: white; }
    .badge-video { background: rgba(220,38,38,.85);  color: white; }
    .badge-pdf   { background: rgba(217,119,6,.85);  color: white; }
    .badge-scan  { background: rgba(124,58,237,.85); color: white; }
    .media-card-body { padding: 6px 8px; }
    .media-card-name { font-size: 10px; font-weight: 600; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .media-card-meta { font-size: 9px; color: #9ca3af; margin-top: 1px; }
    .media-card-del { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; border-radius: 50%; background: rgba(220,38,38,.9); border: none; color: white; cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 12px; line-height: 1; }
    .media-card:hover .media-card-del { display: flex; }

    /* ── Buttons ── */
    .btn-primary  { padding: 8px 18px; background: #6a0f70; color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: background .15s; display: inline-flex; align-items: center; gap: 6px; }
    .btn-primary:hover    { background: #380740; }
    .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
    .btn-outline { padding: 7px 14px; border: 1px solid #e5e7eb; background: white; color: #374151; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
    .btn-outline:hover { border-color: #6a0f70; color: #6a0f70; }
    .btn-danger  { padding: 7px 12px; border: 1px solid #fecaca; background: white; color: #dc2626; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
    .btn-danger:hover { background: #fef2f2; }

    /* ── Modals ── */
    .em-modal-overlay { position: fixed; inset: 0; z-index: 200; background: rgba(14,1,24,.45); display: flex; align-items: center; justify-content: center; }
    .em-modal { background: white; border-radius: 12px; width: 440px; max-width: 96vw; box-shadow: 0 24px 64px rgba(0,0,0,.2); overflow: hidden; }
    .em-modal-head { padding: 16px 20px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
    .em-modal-title { font-size: 14px; font-weight: 700; color: #111827; }
    .em-modal-close { background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 22px; line-height: 1; }
    .em-modal-body  { padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; }
    .em-modal-foot  { padding: 12px 20px; border-top: 1px solid #f3f4f6; display: flex; justify-content: flex-end; gap: 8px; }
    .em-label  { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; display: block; }
    .em-input  { width: 100%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #374151; outline: none; transition: border-color .15s; font-family: inherit; }
    .em-input:focus { border-color: #6a0f70; }

    /* ── Tags row ── */
    .upload-meta-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }

    /* ── Toast ── */
    #em-toast { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .toast-item { padding: 10px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; color: white; box-shadow: 0 4px 16px rgba(0,0,0,.15); animation: slideUp .3s ease; }
    .toast-success { background: #16a34a; }
    .toast-error   { background: #dc2626; }
    @keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
</style>
@endsection

@section('content')
<div id="edu-manage" x-data="eduManage()" x-init="init()">

    {{-- ══ HEADER ══ --}}
    <div id="edu-manage-header">
        <div class="emh-top">
            <div>
                <div class="emh-title">Clinical Library</div>
                <div class="emh-sub">Manage education content — categories, treatments and media</div>
            </div>
            <a href="{{ route('cms.education') }}" class="btn-outline">← Back to Library</a>
        </div>
        <div class="cms-tabs">
            <a href="{{ route('cms.index') }}" class="cms-tab">Patient Clinical Data</a>
            <a href="{{ route('cms.education') }}" class="cms-tab">Generic Education Library</a>
            <a href="{{ route('cms.education.manage') }}" class="cms-tab active">Manage Content</a>
        </div>
    </div>

    {{-- ══ 3-COLUMN BODY ══ --}}
    <div id="edu-manage-body">

        {{-- ── Col 1: Categories ── --}}
        <div class="em-col">
            <div class="em-col-head">
                <div class="em-col-title">Categories</div>
            </div>
            <div class="em-col-body">
                @forelse($categories as $cat)
                <div class="cat-item {{ ($categoryId ?? null) == $cat->id ? 'active' : '' }}">
                    <div class="cat-item-dot" style="background:{{ $cat->color ?? '#6a0f70' }};"></div>
                    <a href="{{ route('cms.education.manage', ['category_id' => $cat->id]) }}"
                       class="cat-item-name">{{ $cat->name }}</a>
                    <span class="cat-item-count">{{ $cat->treatments_count }}</span>
                    <button type="button" class="item-edit-btn" title="Edit"
                            @click="openEditCat({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ $cat->color ?? '#6a0f70' }}', '{{ addslashes($cat->description ?? '') }}')">✎</button>
                    <form method="POST" action="{{ route('cms.education.category.destroy', $cat) }}"
                          onsubmit="return confirm('Delete category and all its treatments?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" class="item-del-btn" title="Delete">×</button>
                    </form>
                </div>
                @empty
                <div style="padding:20px 16px;font-size:12px;color:#9ca3af;text-align:center;">No categories yet.</div>
                @endforelse
            </div>
            <button class="em-add-btn" @click="showCatModal=true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Add Category
            </button>
        </div>

        {{-- ── Col 2: Treatments ── --}}
        <div class="em-col">
            <div class="em-col-head">
                <div class="em-col-title">{{ isset($selectedCategory) ? $selectedCategory->name : 'Treatments' }}</div>
            </div>
            <div class="em-col-body">
                @if(isset($treatments) && $treatments->count())
                @foreach($treatments as $tx)
                <div class="tx-item {{ ($treatmentId ?? null) == $tx->id ? 'active' : '' }}">
                    @if($tx->cover_image_path)
                    <img src="{{ Storage::url($tx->cover_image_path) }}" class="tx-thumb-mini" alt="">
                    @else
                    <div class="tx-thumb-mini">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                    @endif
                    <a href="{{ route('cms.education.manage', ['category_id' => $categoryId, 'treatment_id' => $tx->id]) }}"
                       class="tx-item-name">{{ $tx->title }}</a>
                    <span class="tx-item-count">{{ $tx->media_count ?? 0 }}</span>
                    <button type="button" class="item-edit-btn" title="Edit"
                            @click="openEditTx({{ $tx->id }}, '{{ addslashes($tx->title) }}', '{{ addslashes($tx->description ?? '') }}')">✎</button>
                    <form method="POST" action="{{ route('cms.education.treatment.destroy', $tx) }}"
                          onsubmit="return confirm('Delete this treatment and all its media?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" class="item-del-btn" title="Delete">×</button>
                    </form>
                </div>
                @endforeach
                @elseif(isset($categoryId))
                <div style="padding:20px 16px;font-size:12px;color:#9ca3af;text-align:center;">No treatments yet.</div>
                @else
                <div style="padding:20px 16px;font-size:12px;color:#9ca3af;text-align:center;">Select a category first.</div>
                @endif
            </div>
            @if(isset($categoryId))
            <button class="em-add-btn" @click="showTxModal=true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Add Treatment
            </button>
            @endif
        </div>

        {{-- ── Col 3: Upload + Media ── --}}
        <div class="em-col">
            <div id="right-panel">
                @if(!isset($selectedTreatment))
                <div class="rp-empty">
                    <div class="rp-empty-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                    <div style="font-size:14px;font-weight:700;color:#374151;">Select a treatment</div>
                    <div style="font-size:12px;color:#9ca3af;">to upload and manage media</div>
                </div>
                @else

                {{-- Treatment header --}}
                <div class="tx-detail-head">
                    <div>
                        <div class="tx-detail-title">{{ $selectedTreatment->title }}</div>
                        <div class="tx-detail-cat">{{ $selectedTreatment->category->name ?? '' }}</div>
                        @if($selectedTreatment->description)
                        <div class="tx-detail-desc">{{ $selectedTreatment->description }}</div>
                        @endif
                    </div>
                    <div class="tx-detail-actions">
                        <button type="button" class="btn-outline"
                                @click="openEditTx({{ $selectedTreatment->id }}, '{{ addslashes($selectedTreatment->title) }}', '{{ addslashes($selectedTreatment->description ?? '') }}')">
                            ✎ Edit
                        </button>
                        <form method="POST" action="{{ route('cms.education.treatment.destroy', $selectedTreatment) }}"
                              onsubmit="return confirm('Delete this treatment and all its media?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </div>
                </div>

                {{-- Upload zone --}}
                <div id="upload-zone"
                     @dragover.prevent="dragOver=true" @dragleave="dragOver=false"
                     @drop.prevent="handleDrop($event)"
                     :class="dragOver ? 'drag-over' : ''"
                     @click="$refs.fileInput.click()">
                    <div class="uz-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="uz-title">Drop files here or click to browse</div>
                    <div class="uz-sub">Photos · X-Rays · Videos · PDFs · Scans — Max 100MB each</div>
                    <div class="media-type-row" @click.stop>
                        @foreach(['photo'=>'Photos','xray'=>'X-Rays','video'=>'Video','pdf'=>'PDF','scan'=>'Scan'] as $type=>$label)
                        <button type="button" class="mt-btn" :class="mediaType==='{{ $type }}' ? 'active' : ''" @click="mediaType='{{ $type }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                    <input type="file" x-ref="fileInput" multiple style="display:none;" :accept="acceptTypes" @change="handleFiles($event)">
                </div>

                {{-- Upload meta --}}
                <div class="upload-meta-row" x-show="queue.length > 0" x-cloak>
                    <div>
                        <label class="em-label">Title (optional)</label>
                        <input type="text" x-model="uploadTitle" class="em-input" placeholder="e.g. Before treatment">
                    </div>
                    <div>
                        <label class="em-label">Tags (comma separated)</label>
                        <input type="text" x-model="uploadTags" class="em-input" placeholder="before, implant">
                    </div>
                </div>

                {{-- Queue --}}
                <div class="upload-queue" x-show="queue.length > 0" x-cloak>
                    <template x-for="(item, i) in queue" :key="i">
                        <div class="uq-item">
                            <div class="uq-preview">
                                <template x-if="item.preview"><img :src="item.preview" alt=""></template>
                                <template x-if="!item.preview">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </template>
                            </div>
                            <div class="uq-info">
                                <div class="uq-name" x-text="item.name"></div>
                                <div class="uq-size" x-text="formatSize(item.size)"></div>
                                <div class="uq-progress" x-show="item.progress > 0">
                                    <div class="uq-progress-fill" :style="'width:'+item.progress+'%'"></div>
                                </div>
                            </div>
                            <button class="uq-remove" @click="queue.splice(i,1)">×</button>
                        </div>
                    </template>
                </div>

                {{-- Upload button --}}
                <div x-show="queue.length > 0" x-cloak style="margin-bottom:18px;">
                    <button class="btn-primary" @click="uploadAll()" :disabled="uploading">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span x-text="uploading ? 'Uploading…' : 'Upload ' + queue.length + ' File(s)'"></span>
                    </button>
                </div>

                {{-- ══ LIGHTBOX ══ --}}
<div id="lightbox" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.92);align-items:center;justify-content:center;flex-direction:column;"
     onclick="closeLightbox()">
    <button onclick="closeLightbox()" style="position:absolute;top:16px;right:20px;background:rgba(255,255,255,.15);border:none;color:white;width:36px;height:36px;border-radius:50%;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>
    <div id="lb-content" style="max-width:90vw;max-height:85vh;display:flex;align-items:center;justify-content:center;" onclick="event.stopPropagation()"></div>
    <div id="lb-caption" style="color:rgba(255,255,255,.6);font-size:12px;margin-top:12px;text-align:center;"></div>
</div>
                {{-- Existing media --}}
                @if($media->count() > 0)
                <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:12px;">
                    Uploaded Media <span style="color:#9ca3af;font-weight:400;">({{ $media->count() }})</span>
                </div>

                @foreach(['photo'=>'Photos','xray'=>'X-Rays','video'=>'Videos','pdf'=>'PDFs','scan'=>'Scans'] as $type=>$label)
                @php $typeMedia = $media->where('media_type', $type); @endphp
                @if($typeMedia->count())
                <div style="margin-bottom:16px;">
                    <div class="media-section-label">{{ $label }} ({{ $typeMedia->count() }})</div>
                    <div class="media-grid">
                        @foreach($typeMedia as $m)
                        <div class="media-card">
<div class="media-thumb" onclick="openLightbox('{{ $m->media_type }}','{{ Storage::url($m->file_path) }}','{{ addslashes($m->title ?: basename($m->file_path)) }}')" style="cursor:zoom-in;">                                
    @if(in_array($m->media_type, ['photo','xray']) && $m->file_path)
                                <img src="{{ Storage::url($m->file_path) }}" alt="{{ $m->title }}" loading="lazy">
                                @elseif($m->media_type === 'video')
                                <div class="media-thumb-icon" style="background:#1c1c2e;">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="white" opacity=".8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                                @elseif($m->media_type === 'pdf')
                                <div class="media-thumb-icon" style="background:#fff7ed;">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                @else
                                <div class="media-thumb-icon" style="background:#f5f3ff;">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/></svg>
                                </div>
                                @endif
                                <span class="media-type-badge badge-{{ $m->media_type }}">{{ strtoupper($m->media_type) }}</span>
                            </div>
                            <div class="media-card-body">
                                <div class="media-card-name">{{ $m->title ?: basename($m->file_path) }}</div>
                                <div class="media-card-meta">{{ $m->file_size ? number_format($m->file_size/1024/1024,1).' MB' : '' }}</div>
                            </div>
                            <form method="POST" action="{{ route('cms.education.media.destroy', $m) }}"
                                  onsubmit="return confirm('Delete this file?')" style="display:contents;">
                                @csrf @method('DELETE')
                                <button type="submit" class="media-card-del">×</button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach

                @else
                <div style="text-align:center;padding:20px;font-size:12px;color:#9ca3af;">
                    No media uploaded yet. Use the upload zone above.
                </div>
                @endif

                @endif {{-- end selectedTreatment --}}
            </div>
        </div>

    </div>{{-- /3-col --}}

    {{-- ══ ADD CATEGORY MODAL ══ --}}
    <div class="em-modal-overlay" x-show="showCatModal" x-cloak @click.self="showCatModal=false">
        <div class="em-modal">
            <div class="em-modal-head">
                <span class="em-modal-title">Add Category</span>
                <button class="em-modal-close" @click="showCatModal=false">×</button>
            </div>
            <form method="POST" action="{{ route('cms.education.category.store') }}">
                @csrf
                <div class="em-modal-body">
                    <div>
                        <label class="em-label">Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="name" class="em-input" placeholder="e.g. Implantology" required>
                    </div>
                    <div>
                        <label class="em-label">Description</label>
                        <input type="text" name="description" class="em-input" placeholder="Short description…">
                    </div>
                    <div>
                        <label class="em-label">Color</label>
                        <input type="color" name="color" value="#6a0f70" style="width:44px;height:36px;border:1px solid #e5e7eb;border-radius:5px;cursor:pointer;padding:2px;">
                    </div>
                </div>
                <div class="em-modal-foot">
                    <button type="button" class="btn-outline" @click="showCatModal=false">Cancel</button>
                    <button type="submit" class="btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ EDIT CATEGORY MODAL ══ --}}
    <div class="em-modal-overlay" x-show="showEditCatModal" x-cloak @click.self="showEditCatModal=false">
        <div class="em-modal">
            <div class="em-modal-head">
                <span class="em-modal-title">Edit Category</span>
                <button class="em-modal-close" @click="showEditCatModal=false">×</button>
            </div>
            <form method="POST" :action="'{{ url('content-management/education/category') }}/'+editCatId+'/update'">
                @csrf @method('PUT')
                <div class="em-modal-body">
                    <div>
                        <label class="em-label">Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="name" class="em-input" x-model="editCatName" required>
                    </div>
                    <div>
                        <label class="em-label">Description</label>
                        <input type="text" name="description" class="em-input" x-model="editCatDesc">
                    </div>
                    <div>
                        <label class="em-label">Color</label>
                        <input type="color" name="color" x-model="editCatColor" style="width:44px;height:36px;border:1px solid #e5e7eb;border-radius:5px;cursor:pointer;padding:2px;">
                    </div>
                </div>
                <div class="em-modal-foot">
                    <button type="button" class="btn-outline" @click="showEditCatModal=false">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ ADD TREATMENT MODAL ══ --}}
    <div class="em-modal-overlay" x-show="showTxModal" x-cloak @click.self="showTxModal=false">
        <div class="em-modal">
            <div class="em-modal-head">
                <span class="em-modal-title">Add Treatment</span>
                <button class="em-modal-close" @click="showTxModal=false">×</button>
            </div>
            <form method="POST" action="{{ route('cms.education.treatment.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="category_id" value="{{ $categoryId ?? '' }}">
                <div class="em-modal-body">
                    <div>
                        <label class="em-label">Title <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="title" class="em-input" placeholder="e.g. Dental Implant Placement" required>
                    </div>
                    <div>
                        <label class="em-label">Description</label>
                        <textarea name="description" class="em-input" rows="3" placeholder="Short description…" style="resize:vertical;"></textarea>
                    </div>
                    <div>
                        <label class="em-label">Cover Image</label>
                        <input type="file" name="cover_image" accept="image/*" class="em-input" style="padding:5px;">
                    </div>
                </div>
                <div class="em-modal-foot">
                    <button type="button" class="btn-outline" @click="showTxModal=false">Cancel</button>
                    <button type="submit" class="btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ EDIT TREATMENT MODAL ══ --}}
    <div class="em-modal-overlay" x-show="showEditTxModal" x-cloak @click.self="showEditTxModal=false">
        <div class="em-modal">
            <div class="em-modal-head">
                <span class="em-modal-title">Edit Treatment</span>
                <button class="em-modal-close" @click="showEditTxModal=false">×</button>
            </div>
            <form method="POST" :action="'{{ url('content-management/education/treatment') }}/'+editTxId+'/update'">
                @csrf @method('PUT')
                <div class="em-modal-body">
                    <div>
                        <label class="em-label">Title <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="title" class="em-input" x-model="editTxTitle" required>
                    </div>
                    <div>
                        <label class="em-label">Description</label>
                        <textarea name="description" class="em-input" rows="3" x-model="editTxDesc" style="resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="em-modal-foot">
                    <button type="button" class="btn-outline" @click="showEditTxModal=false">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ TOAST ══ --}}
    <div id="em-toast">
        <template x-for="(t, i) in toasts" :key="i">
            <div class="toast-item" :class="t.type==='error'?'toast-error':'toast-success'" x-text="t.msg"></div>
        </template>
    </div>

</div>
@endsection

@push('scripts')
<script>
function eduManage() {
    return {
        // Modals
        showCatModal:     false,
        showEditCatModal: false,
        showTxModal:      false,
        showEditTxModal:  false,

        // Edit category
        editCatId:    null,
        editCatName:  '',
        editCatColor: '#6a0f70',
        editCatDesc:  '',

        // Edit treatment
        editTxId:    null,
        editTxTitle: '',
        editTxDesc:  '',

        // Upload
        mediaType:   'photo',
        queue:       [],
        uploading:   false,
        dragOver:    false,
        uploadTitle: '',
        uploadTags:  '',

        // Toast
        toasts: [],

        get acceptTypes() {
            return { photo:'image/*', xray:'image/*', video:'video/*', pdf:'application/pdf', scan:'.stl,.dcm,image/*' }[this.mediaType] || '*';
        },

        init() {
            @if(session('success')) this.toast('{{ session("success") }}', 'success'); @endif
            @if(session('error'))   this.toast('{{ session("error") }}',   'error');   @endif
        },

        openEditCat(id, name, color, desc) {
            this.editCatId    = id;
            this.editCatName  = name;
            this.editCatColor = color || '#6a0f70';
            this.editCatDesc  = desc;
            this.showEditCatModal = true;
        },

        openEditTx(id, title, desc) {
            this.editTxId    = id;
            this.editTxTitle = title;
            this.editTxDesc  = desc;
            this.showEditTxModal = true;
        },

        handleFiles(e) {
            Array.from(e.target.files).forEach(f => this.addToQueue(f));
            e.target.value = '';
        },

        handleDrop(e) {
            this.dragOver = false;
            Array.from(e.dataTransfer.files).forEach(f => this.addToQueue(f));
        },

        addToQueue(file) {
            const item = { file, name: file.name, size: file.size, progress: 0, preview: null };
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => { item.preview = e.target.result; };
                reader.readAsDataURL(file);
            }
            this.queue.push(item);
        },

        async uploadAll() {
            if (!this.queue.length || this.uploading) return;
            this.uploading = true;
            const treatmentId = {{ $selectedTreatment->id ?? 'null' }};
            if (!treatmentId) { this.toast('No treatment selected.', 'error'); this.uploading = false; return; }

            const fd = new FormData();
            this.queue.forEach(item => fd.append('files[]', item.file));
            fd.append('media_type', this.mediaType);
            fd.append('title',      this.uploadTitle);
            fd.append('tags',       this.uploadTags);
            fd.append('_token',     '{{ csrf_token() }}');

            this.queue.forEach(item => item.progress = 40);

            try {
                const res  = await fetch(`/content-management/education/treatment/${treatmentId}/upload`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                this.queue.forEach(item => item.progress = 100);
                if (data.success) {
                    this.toast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    this.toast(data.message || 'Upload failed.', 'error');
                    this.uploading = false;
                }
            } catch (e) {
                this.toast('Network error. Please try again.', 'error');
                this.uploading = false;
            }
        },

        formatSize(bytes) {
            if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
            return (bytes/1048576).toFixed(1) + ' MB';
        },

        toast(msg, type = 'success') {
            this.toasts.push({ msg, type });
            setTimeout(() => this.toasts.shift(), 3500);
        },
    };
}
function openLightbox(type, url, caption) {
    const lb      = document.getElementById('lightbox');
    const content = document.getElementById('lb-content');
    const cap     = document.getElementById('lb-caption');
    cap.textContent = caption;
    content.innerHTML = '';

    if (type === 'photo' || type === 'xray') {
        const img = document.createElement('img');
        img.src   = url;
        img.style = 'max-width:90vw;max-height:82vh;border-radius:8px;object-fit:contain;box-shadow:0 8px 40px rgba(0,0,0,.5);';
        content.appendChild(img);
    } else if (type === 'video') {
        const vid    = document.createElement('video');
        vid.src      = url;
        vid.controls = true;
        vid.autoplay = true;
        vid.style    = 'max-width:90vw;max-height:80vh;border-radius:8px;outline:none;';
        content.appendChild(vid);
    } else if (type === 'pdf') {
        const frame = document.createElement('iframe');
        frame.src   = url;
        frame.style = 'width:85vw;height:82vh;border:none;border-radius:8px;background:white;';
        frame.title = caption;
        content.appendChild(frame);
    } else {
        content.innerHTML = `
            <div style="text-align:center;color:white;">
                <div style="font-size:14px;font-weight:700;margin-bottom:8px;">${caption}</div>
                <a href="${url}" download target="_blank"
                   style="padding:10px 24px;background:#6a0f70;color:white;border-radius:8px;text-decoration:none;font-size:13px;font-weight:700;display:inline-block;">
                    Download File
                </a>
            </div>`;
    }

    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lb = document.getElementById('lightbox');
    lb.style.display = 'none';
    document.getElementById('lb-content').innerHTML = '';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

</script>
@endpush