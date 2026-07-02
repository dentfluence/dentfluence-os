{{--
|==========================================================================
| Universal Publish — Panel 1: Master Content
| File: resources/views/marketing/publish/partials/_master-content.blade.php
|
| Sections:
|   • Post type pill selector (Post|Reel|Carousel|Story|Blog|Offer)
|   • Content textarea with live char counter
|   • Emoji / Hashtag / AI-Improve toolbar
|   • Media row (3 thumbnails + Add Media tile)
|   • Call to Action (Optional)
|   • Hashtags
|
| Alpine.js handles: char counter, pill selection, hashtag add/remove.
|==========================================================================
--}}

<div
    x-data="{
        postType: 'post',
        postTypes: ['Post','Reel','Carousel','Story','Blog','Offer'],
        content: 'A confident smile can change everything. ✨\n\nAt Dentfluence, we believe every patient deserves to walk out feeling transformed. Whether it\'s a routine clean or a complete smile makeover — we\'re with you every step of the way.\n\nAdvanced Technology\nGentle & Caring Team\nFlexible Appointments\n\nBook your consultation today!',
        maxChars: 2200,
        hashtags: ['#SmileMakeover','#ConfidentSmile','#DentalCare','#DentfluenceClinic','#HealthyTeeth'],
        newTag: '',
        ctaType: 'Book Appointment',
        ctaUrl: 'https://dentfluence.in/book',
        showEmojiHint: false,
        addHashtag() {
            let tag = this.newTag.trim();
            if(!tag) return;
            if(!tag.startsWith('#')) tag = '#' + tag;
            if(!this.hashtags.includes(tag)) this.hashtags.push(tag);
            this.newTag = '';
        },
        removeHashtag(index) {
            this.hashtags.splice(index, 1);
        },
        get charCount() { return this.content.length; },
        get charColor() {
            const pct = this.content.length / this.maxChars;
            if(pct >= 1) return '#e53e3e';
            if(pct >= 0.85) return '#d97706';
            return '#16a34a';
        }
    }"
    style="padding: 24px 22px; height: 100%;"
>

    {{-- ── SECTION LABEL ② ──────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;">
        <span style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px; height: 20px;
            background: #6a0f70;
            border-radius: 50%;
            font-family: 'Inter', sans-serif;
            font-size: 10px; font-weight: 700;
            color: #fff; flex-shrink: 0;
        ">①</span>
        <span style="
            font-family: 'Inter', sans-serif;
            font-size: 13px; font-weight: 600;
            color: #1e0a2c;
        ">Master Content</span>
        <span style="
            margin-left: auto;
            font-family: 'Inter', sans-serif;
            font-size: 11px; font-weight: 300;
            color: #9b6aad;
        ">Applied to all platforms</span>
    </div>

    {{-- ── POST TYPE PILLS ──────────────────────────────────────── --}}
    <div style="margin-bottom: 16px;">
        <div style="
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        ">
            <template x-for="type in postTypes" :key="type">
                <button
                    type="button"
                    @click="postType = type.toLowerCase()"
                    :style="`
                        display: inline-flex;
                        align-items: center;
                        height: 28px;
                        padding: 0 12px;
                        border-radius: 14px;
                        font-family: 'Inter', sans-serif;
                        font-size: 12px;
                        font-weight: ${postType === type.toLowerCase() ? '600' : '400'};
                        color: ${postType === type.toLowerCase() ? '#ffffff' : '#5a4868'};
                        background: ${postType === type.toLowerCase() ? 'linear-gradient(135deg,#6a0f70 0%,#9b3da0 100%)' : 'rgba(185,92,183,0.08)'};
                        border: 1.5px solid ${postType === type.toLowerCase() ? 'transparent' : 'rgba(185,92,183,0.18)'};
                        cursor: pointer;
                        transition: all 150ms;
                        white-space: nowrap;
                    `"
                    x-text="type"
                ></button>
            </template>
        </div>
    </div>
    {{-- /POST TYPE PILLS --}}

    {{-- ── CONTENT TEXTAREA ─────────────────────────────────────── --}}
    <div style="margin-bottom: 10px; position: relative;">
        <textarea
            x-model="content"
            placeholder="A confident smile can change everything..."
            maxlength="2200"
            rows="7"
            style="
                width: 100%;
                padding: 12px 14px;
                border: 1.5px solid rgba(185,92,183,0.22);
                border-radius: 8px;
                font-family: 'Inter', sans-serif;
                font-size: 13px;
                font-weight: 300;
                color: #1e0a2c;
                line-height: 1.6;
                background: #fdfaff;
                resize: vertical;
                outline: none;
                box-sizing: border-box;
                transition: border-color 150ms;
            "
            onfocus="this.style.borderColor='rgba(106,15,112,0.45)'"
            onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
        ></textarea>

        {{-- Char counter --}}
        <div style="
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            margin-top: 5px;
        ">
            {{-- Colored dot --}}
            <span :style="`
                display: inline-block;
                width: 7px; height: 7px;
                border-radius: 50%;
                background: ${charColor};
                flex-shrink: 0;
            `"></span>
            <span :style="`
                font-family: 'Inter', sans-serif;
                font-size: 11.5px;
                font-weight: 400;
                color: ${charColor};
            `" x-text="`${charCount} / ${maxChars}`"></span>
        </div>
    </div>
    {{-- /CONTENT TEXTAREA --}}

    {{-- ── TOOLBAR: Emoji / Hashtag / AI Improve ────────────────── --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 18px;
        padding: 0 2px;
    ">
        {{-- Emoji --}}
        <button type="button"
            title="Add emoji"
            style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px; height: 32px;
                border-radius: 6px;
                border: 1px solid rgba(185,92,183,0.18);
                background: transparent;
                cursor: pointer;
                font-size: 15px;
                transition: background 150ms;
            "
            onmouseover="this.style.background='rgba(185,92,183,0.07)'"
            onmouseout="this.style.background='transparent'"
        ></button>

        {{-- Hashtag --}}
        <button type="button"
            title="Add hashtag"
            style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px; height: 32px;
                border-radius: 6px;
                border: 1px solid rgba(185,92,183,0.18);
                background: transparent;
                cursor: pointer;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
                font-weight: 600;
                color: #6a0f70;
                transition: background 150ms;
            "
            onmouseover="this.style.background='rgba(185,92,183,0.07)'"
            onmouseout="this.style.background='transparent'"
        >#</button>

        {{-- AI Improve --}}
        <button type="button"
            title="AI improve"
            style="
                display: inline-flex;
                align-items: center;
                gap: 5px;
                height: 32px;
                padding: 0 12px;
                border-radius: 6px;
                border: 1px solid rgba(185,92,183,0.18);
                background: transparent;
                cursor: pointer;
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                font-weight: 500;
                color: #6a0f70;
                transition: background 150ms;
            "
            onmouseover="this.style.background='rgba(185,92,183,0.07)'"
            onmouseout="this.style.background='transparent'"
        >
            ✨ <span>AI Improve</span>
        </button>

        {{-- Spacer --}}
        <div style="flex:1;"></div>

        {{-- Translate --}}
        <button type="button"
            title="Translate"
            style="
                display: inline-flex;
                align-items: center;
                gap: 5px;
                height: 32px;
                padding: 0 10px;
                border-radius: 6px;
                border: 1px solid rgba(185,92,183,0.18);
                background: transparent;
                cursor: pointer;
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                font-weight: 400;
                color: #5a4868;
                transition: background 150ms;
            "
            onmouseover="this.style.background='rgba(185,92,183,0.07)'"
            onmouseout="this.style.background='transparent'"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
            </svg>
            Translate
        </button>
    </div>
    {{-- /TOOLBAR --}}

    {{-- ── MEDIA ROW ─────────────────────────────────────────────── --}}
    <div style="margin-bottom: 20px;">
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        ">
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                font-weight: 500;
                color: #5a4868;
            ">Media</span>
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 300;
                color: #9b6aad;
            ">3 / 10 files</span>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">

            {{-- Thumbnail 1 --}}
            <div style="position:relative;width:72px;height:72px;border-radius:6px;overflow:hidden;cursor:pointer;background:#f0e8f7;flex-shrink:0;">
                <div style="
                    width:100%;height:100%;
                    background: linear-gradient(135deg,#d4b8e0 0%,#e8d5f5 100%);
                    display:flex;align-items:center;justify-content:center;
                    font-size:22px;
                "></div>
                {{-- Remove --}}
                <button type="button" style="
                    position:absolute;top:3px;right:3px;
                    width:18px;height:18px;
                    background:rgba(0,0,0,0.55);
                    border:none;border-radius:50%;
                    color:#fff;font-size:9px;
                    display:flex;align-items:center;justify-content:center;
                    cursor:pointer;line-height:1;
                ">✕</button>
            </div>

            {{-- Thumbnail 2 --}}
            <div style="position:relative;width:72px;height:72px;border-radius:6px;overflow:hidden;cursor:pointer;flex-shrink:0;">
                <div style="
                    width:100%;height:100%;
                    background: linear-gradient(135deg,#b8d4e0 0%,#d5eaf5 100%);
                    display:flex;align-items:center;justify-content:center;
                    font-size:22px;
                "></div>
                <button type="button" style="
                    position:absolute;top:3px;right:3px;
                    width:18px;height:18px;
                    background:rgba(0,0,0,0.55);
                    border:none;border-radius:50%;
                    color:#fff;font-size:9px;
                    display:flex;align-items:center;justify-content:center;
                    cursor:pointer;line-height:1;
                ">✕</button>
            </div>

            {{-- Thumbnail 3 --}}
            <div style="position:relative;width:72px;height:72px;border-radius:6px;overflow:hidden;cursor:pointer;flex-shrink:0;">
                <div style="
                    width:100%;height:100%;
                    background: linear-gradient(135deg,#e0d4b8 0%,#f5ebd5 100%);
                    display:flex;align-items:center;justify-content:center;
                    font-size:22px;
                "></div>
                <button type="button" style="
                    position:absolute;top:3px;right:3px;
                    width:18px;height:18px;
                    background:rgba(0,0,0,0.55);
                    border:none;border-radius:50%;
                    color:#fff;font-size:9px;
                    display:flex;align-items:center;justify-content:center;
                    cursor:pointer;line-height:1;
                ">✕</button>
            </div>

            {{-- Add Media tile --}}
            <div style="
                width: 72px; height: 72px;
                border: 1.5px dashed rgba(185,92,183,0.35);
                border-radius: 6px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                cursor: pointer;
                background: rgba(185,92,183,0.03);
                flex-shrink: 0;
                transition: background 150ms;
            "
            onmouseover="this.style.background='rgba(185,92,183,0.07)'"
            onmouseout="this.style.background='rgba(185,92,183,0.03)'"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.55)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span style="
                    font-family: 'Inter', sans-serif;
                    font-size: 9px;
                    font-weight: 400;
                    color: #9b6aad;
                    text-align: center;
                    line-height: 1.3;
                ">Add Media<br>or library</span>
            </div>

        </div>
    </div>
    {{-- /MEDIA ROW --}}

    {{-- ── CALL TO ACTION ───────────────────────────────────────── --}}
    <div style="margin-bottom: 20px;">
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        ">
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                font-weight: 500;
                color: #5a4868;
            ">Call to Action</span>
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 300;
                color: #9b6aad;
                background: rgba(185,92,183,0.08);
                padding: 2px 7px;
                border-radius: 10px;
            ">Optional</span>
        </div>

        <div style="display:flex;gap:8px;align-items:center;">
            {{-- CTA Type dropdown --}}
            <select
                x-model="ctaType"
                style="
                    flex: 0 0 auto;
                    height: 36px;
                    padding: 0 28px 0 10px;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    color: #1e0a2c;
                    background: #faf3fb url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236a0f70' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E\") no-repeat right 9px center;
                    appearance: none;
                    outline: none;
                    cursor: pointer;
                "
            >
                <option>Book Appointment</option>
                <option>Learn More</option>
                <option>Call Now</option>
                <option>Get Offer</option>
                <option>Visit Website</option>
                <option>Send Message</option>
            </select>

            {{-- URL field --}}
            <div style="flex:1;display:flex;align-items:center;position:relative;">
                <input
                    type="url"
                    x-model="ctaUrl"
                    placeholder="https://dentfluence.in/book"
                    style="
                        width: 100%;
                        height: 36px;
                        padding: 0 36px 0 10px;
                        border: 1px solid rgba(185,92,183,0.22);
                        border-radius: 6px;
                        font-family: 'Inter', sans-serif;
                        font-size: 12.5px;
                        color: #1e0a2c;
                        background: #faf3fb;
                        outline: none;
                        box-sizing: border-box;
                    "
                >
                {{-- Copy icon --}}
                <button type="button"
                    title="Copy URL"
                    style="
                        position: absolute;
                        right: 8px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: transparent;
                        border: none;
                        cursor: pointer;
                        color: #9b6aad;
                        display: flex;
                        align-items: center;
                    "
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    {{-- /CALL TO ACTION --}}

    {{-- ── HASHTAGS ──────────────────────────────────────────────── --}}
    <div>
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        ">
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                font-weight: 500;
                color: #5a4868;
            ">Hashtags</span>
            <span style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 300;
                color: #9b6aad;
            " x-text="`${hashtags.length} / 30`"></span>
        </div>

        {{-- Tag chips --}}
        <div style="
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        ">
            <template x-for="(tag, index) in hashtags" :key="tag">
                <span style="
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 10px 4px 10px;
                    background: rgba(185,92,183,0.10);
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 14px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12px;
                    font-weight: 400;
                    color: #6a0f70;
                ">
                    <span x-text="tag"></span>
                    <button
                        type="button"
                        @click="removeHashtag(index)"
                        style="
                            background:transparent;
                            border:none;
                            cursor:pointer;
                            color:#9b6aad;
                            font-size:11px;
                            line-height:1;
                            padding:0;
                            display:flex;align-items:center;
                        "
                        title="Remove hashtag"
                    >×</button>
                </span>
            </template>
        </div>

        {{-- Add tag input --}}
        <div style="display:flex;gap:6px;align-items:center;">
            <input
                type="text"
                x-model="newTag"
                placeholder="Type to add hashtag..."
                @keydown.enter.prevent="addHashtag()"
                @keydown.comma.prevent="addHashtag()"
                style="
                    flex: 1;
                    height: 34px;
                    padding: 0 10px;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    color: #1e0a2c;
                    background: #faf3fb;
                    outline: none;
                "
            >
            <button
                type="button"
                @click="addHashtag()"
                style="
                    height: 34px;
                    padding: 0 14px;
                    background: rgba(106,15,112,0.08);
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12px;
                    font-weight: 500;
                    color: #6a0f70;
                    cursor: pointer;
                    transition: background 150ms;
                "
                onmouseover="this.style.background='rgba(106,15,112,0.15)'"
                onmouseout="this.style.background='rgba(106,15,112,0.08)'"
            >Add</button>
        </div>

        {{-- Suggested tags --}}
        <div style="
            margin-top: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 300;
            color: #9b6aad;
        ">
            Suggested:
            @foreach(['#DentalClinic','#ToothFairy','#OralHealth','#SmileGoals'] as $suggestion)
            <span
                style="cursor:pointer;text-decoration:underline;margin-left:5px;"
                onclick="/* Alpine handles this via parent scope */"
            >{{ $suggestion }}</span>
            @endforeach
        </div>

    </div>
    {{-- /HASHTAGS --}}

</div>{{-- /x-data master content --}}
