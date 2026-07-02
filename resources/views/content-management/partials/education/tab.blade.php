{{-- Education Library Tab --}}
<div x-data="eduTab()" x-init="init()" style="padding:20px;">

    {{-- Category strip --}}
    <div style="margin-bottom:20px;">
        <div style="font-size:13px;font-weight:700;color:#111827;margin-bottom:12px;font-family:'Cormorant Garamond',serif;font-size:16px;">Browse by Category</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" @click="filterCat(null)"
                    :style="activeCat===null ? 'background:#6a0f70;color:white;border-color:#6a0f70;' : 'background:white;color:#6b7280;border-color:#e5e7eb;'"
                    style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1.5px solid;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;transition:all .15s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                All Categories
            </button>
            <template x-for="cat in categories" :key="cat.id">
                <button type="button" @click="filterCat(cat.id)"
                        :style="activeCat===cat.id ? 'background:#6a0f70;color:white;border-color:#6a0f70;' : 'background:white;color:#6b7280;border-color:#e5e7eb;'"
                        style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1.5px solid;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;transition:all .15s;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span x-text="cat.name"></span>
                    <span x-text="'('+cat.items_count+')'" style="opacity:.6;font-weight:400;"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Items grid --}}
    <div style="font-size:15px;font-weight:700;color:#111827;margin-bottom:14px;font-family:'Cormorant Garamond',serif;" x-text="activeCat ? (categories.find(c=>c.id===activeCat)?.name+' Treatments') : 'All Treatments'"></div>

    <div x-show="loading" style="text-align:center;padding:40px;color:#9ca3af;">
        <div style="width:28px;height:28px;border:3px solid #e9d5ff;border-top-color:#6a0f70;border-radius:50%;animation:cms-spin .7s linear infinite;margin:0 auto 10px;"></div>
        Loading…
    </div>

    <div x-show="!loading && items.length === 0" style="text-align:center;padding:60px;color:#9ca3af;">
        <div style="font-size:13px;font-weight:600;color:#374151;">No content yet</div>
        <div style="font-size:12px;margin-top:4px;">Add educational content from Settings → Clinical Library.</div>
    </div>

    <div x-show="!loading && items.length > 0"
         style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        <template x-for="item in items" :key="item.id">
            <div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:white;transition:all .15s;cursor:pointer;"
                 onmouseover="this.style.borderColor='#b95cb7';this.style.boxShadow='0 4px 16px rgba(106,15,112,.1)';this.style.transform='translateY(-1px)'"
                 onmouseout="this.style.borderColor='#e5e7eb';this.style.boxShadow='none';this.style.transform='none'">

                {{-- Thumbnail --}}
                <div style="position:relative;aspect-ratio:16/9;background:#f3f4f6;overflow:hidden;">
                    <img :src="item.thumbnail ?? 'https://placehold.co/400x225/f3f4f6/9ca3af?text='+encodeURIComponent(item.title)"
                         :alt="item.title" loading="lazy"
                         style="width:100%;height:100%;object-fit:cover;">

                    {{-- Type badge --}}
                    <div :style="'position:absolute;top:8px;left:8px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:700;color:white;background:'+(item.media_type==='video'?'#1f2937':item.media_type==='xray'?'#2563eb':'#16a34a')"
                         x-text="item.media_type==='xray'?'X-Ray':(item.media_type?.charAt(0).toUpperCase()+item.media_type?.slice(1))">
                    </div>

                    {{-- Play button for videos --}}
                    <template x-if="item.media_type==='video'">
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);">
                            <div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#374151" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                    </template>

                    {{-- Duration / count badge --}}
                    <template x-if="item.duration">
                        <div style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,.7);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:3px;"
                             x-text="Math.floor(item.duration/60)+':'+(String(item.duration%60).padStart(2,'0'))">
                        </div>
                    </template>
                    <template x-if="!item.duration && (item.photo_count+item.xray_count+item.video_count)>0">
                        <div style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,.7);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:3px;"
                             x-text="''+(item.photo_count+item.xray_count+item.video_count)">
                        </div>
                    </template>
                </div>

                {{-- Body --}}
                <div style="padding:12px;">
                    <div x-text="item.title" style="font-size:13px;font-weight:700;color:#111827;margin-bottom:4px;"></div>
                    <div x-text="item.description" style="font-size:11px;color:#6b7280;margin-bottom:10px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"></div>
                    <div style="display:flex;align-items:center;gap:12px;font-size:11px;color:#9ca3af;">
                        <div x-show="item.photo_count>0" style="display:flex;flex-direction:column;align-items:center;">
                            <strong x-text="item.photo_count" style="font-size:13px;font-weight:800;color:#374151;"></strong>
                            <span>Photos</span>
                        </div>
                        <div x-show="item.xray_count>0" style="display:flex;flex-direction:column;align-items:center;">
                            <strong x-text="item.xray_count" style="font-size:13px;font-weight:800;color:#374151;"></strong>
                            <span>X-Rays</span>
                        </div>
                        <div x-show="item.video_count>0" style="display:flex;flex-direction:column;align-items:center;">
                            <strong x-text="item.video_count" style="font-size:13px;font-weight:800;color:#374151;"></strong>
                            <span>Videos</span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div style="padding:8px 12px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                    <button type="button"
                            style="padding:5px 14px;font-size:11px;font-weight:600;border:1px solid #e5e7eb;border-radius:5px;background:white;color:#374151;cursor:pointer;"
                            onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'"
                            onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                        View
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Footer note --}}
    <div style="margin-top:24px;padding:10px;font-size:11px;color:#9ca3af;display:flex;align-items:center;gap:6px;border-top:1px solid #f3f4f6;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        This library contains educational and reference content for clinical learning and patient education. All content is generic and not linked to individual patients.
    </div>
</div>

<script>
function eduTab() {
    return {
        categories: [],
        items: [],
        activeCat: null,
        loading: false,

        async init() {
            this.loading = true;
            try {
                const r = await fetch('/content-management/education', {
                    headers: { 'Accept': 'application/json' }
                });
                const d = await r.json();
                this.categories = d.categories ?? [];
                this.items      = d.items      ?? [];
            } catch(e) {
                // show empty state
            }
            this.loading = false;
        },

        filterCat(id) {
            this.activeCat = id;
            if (!id) {
                this.items = this._allItems;
            } else {
                this.items = this._allItems.filter(i => i.category_id === id);
            }
        },

        get _allItems() {
            return this.__allItems ?? [];
        },
    }
}
</script>                                                                                                                                                                                                                                                                                                                                                                                                     