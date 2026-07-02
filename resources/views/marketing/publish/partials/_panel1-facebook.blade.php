{{--
|==========================================================================
| Universal Publish — Panel 1: Facebook-specific fields
| File: resources/views/marketing/publish/partials/_panel1-facebook.blade.php
|
| Shows when activeTab === 'facebook'
| Sections:
|   • Post type: Post | Reel | Story
|   • Content textarea
|   • Link Preview section (URL + editable title/description)
|   • Hashtags
|==========================================================================
--}}

<div
    x-data="{
        fbType: 'post',
        fbTypes: ['Post','Reel','Story'],
        content: 'A confident smile can change everything. ✨\n\nAt Dentfluence, we believe every patient deserves to walk out feeling transformed. Whether it\'s a routine clean or a complete smile makeover — we\'re with you every step of the way.\n\nAdvanced Technology\nGentle & Caring Team\nFlexible Appointments\n\nBook your consultation today!\nhttps://dentfluence.in/book',
        linkUrl: 'https://dentfluence.in/book',
        linkTitle: 'Book Your Consultation — Dentfluence',
        linkDesc: 'Schedule your dental appointment online. Expert care, gentle approach.',
        showLinkPreview: true,
        hashtags: ['#SmileMakeover','#DentalCare','#DentfluenceClinic'],
        newTag: '',
        addHashtag() {
            let tag = this.newTag.trim();
            if(!tag) return;
            if(!tag.startsWith('#')) tag = '#' + tag;
            if(!this.hashtags.includes(tag)) this.hashtags.push(tag);
            this.newTag = '';
        },
        removeHashtag(i) { this.hashtags.splice(i,1); },
        get charCount() { return this.content.length; },
        get charColor() {
            const pct = this.content.length / 63206;
            if(pct >= 1) return '#e53e3e';
            if(pct >= 0.85) return '#d97706';
            return '#16a34a';
        }
    }"
    style="padding:24px 22px;height:100%;overflow-y:auto;"
>

    {{-- ── SECTION LABEL ── --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;">
        <span style="
            display:inline-flex;align-items:center;justify-content:center;
            width:20px;height:20px;background:#6a0f70;border-radius:50%;
            font-family:'Inter',sans-serif;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;
        ">①</span>
        <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;">
            Facebook Content
        </span>
        <span style="
            margin-left:auto;
            display:inline-flex;align-items:center;gap:5px;
            background:#eff6ff;border:1px solid #93c5fd;border-radius:20px;
            padding:3px 10px;
            font-family:'Inter',sans-serif;font-size:10.5px;font-weight:600;color:#1d4ed8;
        ">
            Facebook
        </span>
    </div>

    {{-- ── POST TYPE PILLS ── --}}
    <div style="margin-bottom:16px;">
        <div style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;margin-bottom:7px;">Post Type</div>
        <div style="display:flex;gap:6px;">
            <template x-for="type in fbTypes" :key="type">
                <button
                    type="button"
                    @click="fbType = type"
                    :style="`
                        flex:1;padding:7px 8px;text-align:center;
                        font-family:'Inter',sans-serif;font-size:12px;font-weight:${fbType===type?'600':'400'};
                        color:${fbType===type?'#ffffff':'#5a4868'};
                        background:${fbType===type?'linear-gradient(135deg,#6a0f70,#9b3da0)':'transparent'};
                        border:1.5px solid ${fbType===type?'transparent':'rgba(185,92,183,0.22)'};
                        border-radius:8px;cursor:pointer;transition:all 150ms;
                    `"
                    x-text="type"
                ></button>
            </template>
        </div>
    </div>

    {{-- ── CONTENT TEXTAREA ── --}}
    <div style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">Post Text</label>
            <span :style="`font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:${charColor};`">
                <span x-text="charCount"></span> chars
            </span>
        </div>
        <textarea
            x-model="content"
            rows="7"
            placeholder="Write your Facebook post…"
            style="
                width:100%;box-sizing:border-box;padding:10px 12px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:8px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;line-height:1.6;
                background:#faf3fb;outline:none;resize:vertical;
            "
        ></textarea>
    </div>

    {{-- ── LINK PREVIEW SECTION ── --}}
    <div style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#1e0a2c;">
                Link Preview
            </div>
            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;">
                <input type="checkbox" x-model="showLinkPreview" style="accent-color:#6a0f70;width:13px;height:13px;">
                <span style="font-family:'Inter',sans-serif;font-size:10.5px;color:#5a4868;">Show preview</span>
            </label>
        </div>

        <div x-show="showLinkPreview" x-transition>
            {{-- Link URL --}}
            <div style="margin-bottom:8px;">
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">Link URL</label>
                <input type="url" x-model="linkUrl" placeholder="https://…" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                ">
            </div>

            {{-- Preview card mock --}}
            <div style="
                border:1.5px solid rgba(185,92,183,0.18);border-radius:8px;
                overflow:hidden;background:#fff;
            ">
                {{-- Image placeholder --}}
                <div style="
                    height:80px;background:linear-gradient(135deg,#f3e8ff,#fce7f3);
                    display:flex;align-items:center;justify-content:center;
                    font-size:26px;
                "></div>
                {{-- OG text --}}
                <div style="padding:10px 12px;">
                    <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.04em;">
                        dentfluence.in
                    </div>
                    <input type="text" x-model="linkTitle" style="
                        width:100%;box-sizing:border-box;padding:3px 0;
                        border:none;border-bottom:1px dashed rgba(185,92,183,0.22);
                        font-family:'Inter',sans-serif;font-size:12.5px;font-weight:600;color:#1e0a2c;
                        background:transparent;outline:none;margin-bottom:4px;
                    ">
                    <input type="text" x-model="linkDesc" style="
                        width:100%;box-sizing:border-box;padding:3px 0;
                        border:none;border-bottom:1px dashed rgba(185,92,183,0.22);
                        font-family:'Inter',sans-serif;font-size:11px;color:#5a4868;
                        background:transparent;outline:none;
                    ">
                    <div style="font-family:'Inter',sans-serif;font-size:9.5px;color:#9b7aaa;margin-top:4px;">
                        Click fields above to edit the link preview title and description.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── HASHTAGS ── --}}
    <div>
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:7px;">
            Hashtags
        </label>
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
        <div style="display:flex;gap:6px;">
            <input type="text" x-model="newTag" @keydown.enter.prevent="addHashtag()" placeholder="#NewHashtag" style="
                flex:1;padding:7px 10px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                background:#faf3fb;outline:none;
            ">
            <button type="button" @click="addHashtag()" style="
                padding:7px 14px;background:#6a0f70;color:#fff;
                border:none;border-radius:6px;cursor:pointer;
                font-family:'Inter',sans-serif;font-size:12px;font-weight:600;
            ">Add</button>
        </div>
    </div>

</div>
