{{--
|==========================================================================
| Universal Publish — Panel 1: Instagram-specific fields
| File: resources/views/marketing/publish/partials/_panel1-instagram.blade.php
|
| Shows when activeTab === 'instagram'
| Sections:
|   • Platform badge + char limit (2200)
|   • Content textarea with live char counter
|   • Post type: Post | Reel | Carousel | Story
|   • Carousel slide manager (shown when Carousel selected)
|   • Alt text field for accessibility
|   • Hashtags
|==========================================================================
--}}

<div
    x-data="{
        igType: 'post',
        igTypes: ['Post','Reel','Carousel','Story'],
        content: 'A confident smile can change everything. ✨\n\nAt Dentfluence, we believe every patient deserves to walk out feeling transformed.\n\nAdvanced Technology\nGentle & Caring Team\nFlexible Appointments\n\nBook your consultation today!',
        maxChars: 2200,
        altText: '',
        hashtags: ['#SmileMakeover','#ConfidentSmile','#DentalCare','#DentfluenceClinic','#HealthyTeeth'],
        newTag: '',
        slides: ['Slide 1 — Before & After', 'Slide 2 — Our Team', 'Slide 3 — Book Now'],
        newSlide: '',
        addHashtag() {
            let tag = this.newTag.trim();
            if(!tag) return;
            if(!tag.startsWith('#')) tag = '#' + tag;
            if(!this.hashtags.includes(tag)) this.hashtags.push(tag);
            this.newTag = '';
        },
        removeHashtag(i) { this.hashtags.splice(i,1); },
        addSlide() {
            let s = this.newSlide.trim();
            if(!s) return;
            this.slides.push(s);
            this.newSlide = '';
        },
        removeSlide(i) { this.slides.splice(i,1); },
        get charCount() { return this.content.length; },
        get charColor() {
            const pct = this.content.length / this.maxChars;
            if(pct >= 1) return '#e53e3e';
            if(pct >= 0.85) return '#d97706';
            return '#16a34a';
        }
    }"
    style="padding:24px 22px;height:100%;overflow-y:auto;"
>

    {{-- ── SECTION LABEL ① ─────────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;">
        <span style="
            display:inline-flex;align-items:center;justify-content:center;
            width:20px;height:20px;background:#6a0f70;border-radius:50%;
            font-family:'Inter',sans-serif;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;
        ">①</span>
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;">
            Instagram Content
        </span>
        {{-- Platform badge --}}
        <span style="
            margin-left:auto;
            display:inline-flex;align-items:center;gap:5px;
            background:#fce7f3;border:1px solid #f9a8d4;border-radius:20px;
            padding:3px 10px;
            font-family:'Inter',sans-serif;font-size:10.5px;font-weight:600;color:#be185d;
        ">
            Instagram
        </span>
    </div>

    {{-- ── POST TYPE PILLS ─────────────────────────────────────────── --}}
    <div style="margin-bottom:16px;">
        <div style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;margin-bottom:7px;">
            Post Type
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <template x-for="type in igTypes" :key="type">
                <button
                    type="button"
                    @click="igType = type"
                    :style="`
                        padding:5px 14px;
                        font-family:'Inter',sans-serif;font-size:12px;font-weight:${igType===type?'600':'400'};
                        color:${igType===type?'#ffffff':'#5a4868'};
                        background:${igType===type?'linear-gradient(135deg,#6a0f70,#9b3da0)':'transparent'};
                        border:1.5px solid ${igType===type?'transparent':'rgba(185,92,183,0.22)'};
                        border-radius:20px;cursor:pointer;
                        box-shadow:${igType===type?'0 2px 6px rgba(106,15,112,0.25)':'none'};
                        transition:all 150ms;
                    `"
                    x-text="type"
                ></button>
            </template>
        </div>
    </div>

    {{-- ── CONTENT TEXTAREA ────────────────────────────────────────── --}}
    <div style="margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">Caption</label>
            <span :style="`font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:${charColor};`">
                <span x-text="charCount"></span> / <span x-text="maxChars"></span>
            </span>
        </div>
        <textarea
            x-model="content"
            rows="7"
            placeholder="Write your Instagram caption…"
            style="
                width:100%;box-sizing:border-box;
                padding:10px 12px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:8px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;line-height:1.6;
                background:#faf3fb;outline:none;resize:vertical;
            "
        ></textarea>
        {{-- Char limit note --}}
        <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-top:4px;">
            Instagram allows up to 2,200 characters for captions.
        </div>
    </div>

    {{-- ── CAROUSEL SLIDE MANAGER (only when Carousel selected) ──── --}}
    <div x-show="igType === 'Carousel'" x-transition style="margin-bottom:16px;">
        <div style="
            background:#f3e8ff;border:1px solid rgba(147,51,234,0.20);
            border-radius:8px;padding:14px;
        ">
            <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#6a0f70;margin-bottom:10px;display:flex;align-items:center;gap:5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><polyline points="8 21 12 17 16 21"/></svg>
                Carousel Slides
            </div>

            {{-- Slide list --}}
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;">
                <template x-for="(slide, i) in slides" :key="i">
                    <div style="
                        display:flex;align-items:center;gap:8px;
                        background:#fff;border:1px solid rgba(147,51,234,0.15);
                        border-radius:6px;padding:7px 10px;
                    ">
                        <span style="
                            font-family:'Inter',sans-serif;font-size:10px;font-weight:700;
                            color:#9b3da0;background:#f3e8ff;border-radius:4px;
                            padding:2px 6px;flex-shrink:0;
                        " x-text="'S' + (i+1)"></span>
                        <span style="flex:1;font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;" x-text="slide"></span>
                        <button type="button" @click="removeSlide(i)" style="
                            background:none;border:none;cursor:pointer;color:#e53e3e;
                            padding:2px;line-height:1;flex-shrink:0;
                        ">✕</button>
                    </div>
                </template>
            </div>

            {{-- Add slide input --}}
            <div style="display:flex;gap:6px;">
                <input
                    type="text"
                    x-model="newSlide"
                    @keydown.enter.prevent="addSlide()"
                    placeholder="Add slide description…"
                    style="
                        flex:1;padding:7px 10px;
                        border:1px solid rgba(147,51,234,0.22);border-radius:6px;
                        font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                        background:#fff;outline:none;
                    "
                >
                <button type="button" @click="addSlide()" style="
                    padding:7px 12px;
                    background:#6a0f70;color:#fff;
                    border:none;border-radius:6px;cursor:pointer;
                    font-family:'Inter',sans-serif;font-size:12px;font-weight:600;
                ">+ Add</button>
            </div>
        </div>
    </div>

    {{-- ── ALT TEXT ────────────────────────────────────────────────── --}}
    <div style="margin-bottom:16px;">
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">
            Alt Text
            <span style="color:#9b7aaa;font-weight:400;margin-left:4px;">(Accessibility)</span>
        </label>
        <input
            type="text"
            x-model="altText"
            placeholder="Describe your image for screen readers…"
            style="
                width:100%;box-sizing:border-box;
                padding:8px 10px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;
                background:#faf3fb;outline:none;
            "
        >
        <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-top:3px;">
            Good alt text improves reach for visually impaired audiences.
        </div>
    </div>

    {{-- ── HASHTAGS ────────────────────────────────────────────────── --}}
    <div>
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:7px;">
            Hashtags
        </label>
        {{-- Tag chips --}}
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
            <template x-for="(tag, i) in hashtags" :key="i">
                <span style="
                    display:inline-flex;align-items:center;gap:4px;
                    background:rgba(106,15,112,0.08);border:1px solid rgba(106,15,112,0.18);
                    border-radius:20px;padding:3px 10px;
                    font-family:'Inter',sans-serif;font-size:11px;color:#6a0f70;
                ">
                    <span x-text="tag"></span>
                    <button type="button" @click="removeHashtag(i)" style="background:none;border:none;cursor:pointer;color:#9b3da0;font-size:11px;padding:0;line-height:1;">✕</button>
                </span>
            </template>
        </div>
        {{-- Add hashtag --}}
        <div style="display:flex;gap:6px;">
            <input
                type="text"
                x-model="newTag"
                @keydown.enter.prevent="addHashtag()"
                placeholder="#NewHashtag"
                style="
                    flex:1;padding:7px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                "
            >
            <button type="button" @click="addHashtag()" style="
                padding:7px 14px;
                background:#6a0f70;color:#fff;
                border:none;border-radius:6px;cursor:pointer;
                font-family:'Inter',sans-serif;font-size:12px;font-weight:600;
            ">Add</button>
        </div>
    </div>

</div>
