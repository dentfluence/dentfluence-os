{{--
|==========================================================================
| Universal Publish — Panel 1: Blog-specific fields
| File: resources/views/marketing/publish/partials/_panel1-blog.blade.php
|
| Shows when activeTab === 'blog'
| Sections:
|   • Rich text editor placeholder (textarea styled as editor)
|   • SEO fields: Title, Slug, Meta Description
|   • Category dropdown
|   • Tags
|==========================================================================
--}}

<div
    x-data="{
        blogTitle: 'How a Confident Smile Can Transform Your Life',
        blogSlug: 'how-a-confident-smile-can-transform-your-life',
        metaDesc: 'Discover how modern dental care at Dentfluence can give you the smile you\'ve always wanted. Expert team, gentle approach.',
        category: 'dental-tips',
        blogContent: 'Introduction\n\nA confident smile does more than just look good — it changes how you feel, how others perceive you, and how you navigate every social interaction.\n\nAt Dentfluence, we\'ve seen firsthand how a smile makeover can genuinely transform a patient\'s life.\n\nWhat Our Patients Say\n\n"I used to cover my mouth every time I laughed. Not anymore." — Priya M.\n\nThe Science Behind It\n\nStudies show that people who smile more are perceived as more trustworthy, competent, and approachable. And when your smile looks great, you smile more naturally.\n\nConclusion\n\nWhether you need a simple whitening or a full restoration, Dentfluence is here to help. Book your consultation today.',
        tags: ['Dental Tips', 'Smile Makeover', 'Oral Health', 'Dentfluence'],
        newTag: '',
        slugLocked: false,
        autoSlug() {
            if(!this.slugLocked) {
                this.blogSlug = this.blogTitle
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-');
            }
        },
        addTag() {
            let t = this.newTag.trim();
            if(!t || this.tags.includes(t)) return;
            this.tags.push(t);
            this.newTag = '';
        },
        removeTag(i) { this.tags.splice(i,1); },
        get metaLength() { return this.metaDesc.length; },
        get metaColor() {
            if(this.metaDesc.length > 160) return '#e53e3e';
            if(this.metaDesc.length > 140) return '#d97706';
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
            Blog Post
        </span>
        <span style="
            margin-left:auto;
            display:inline-flex;align-items:center;gap:5px;
            background:#fef3c7;border:1px solid #fcd34d;border-radius:20px;
            padding:3px 10px;
            font-family:'Inter',sans-serif;font-size:10.5px;font-weight:600;color:#92400e;
        ">
            Blog
        </span>
    </div>

    {{-- ── BLOG TITLE ──────────────────────────────────────────────── --}}
    <div style="margin-bottom:14px;">
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">
            Post Title
        </label>
        <input
            type="text"
            x-model="blogTitle"
            @input="autoSlug()"
            placeholder="Enter your blog post title…"
            style="
                width:100%;box-sizing:border-box;
                padding:9px 12px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;
                background:#faf3fb;outline:none;
            "
        >
    </div>

    {{-- ── SLUG ────────────────────────────────────────────────────── --}}
    <div style="margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">
                URL Slug
            </label>
            <button type="button" @click="slugLocked = !slugLocked" :style="`
                font-family:'Inter',sans-serif;font-size:10px;font-weight:500;
                color:${slugLocked ? '#e53e3e' : '#6a0f70'};
                background:none;border:none;cursor:pointer;padding:0;
            `" x-text="slugLocked ? 'Locked — click to unlock' : 'Auto-generating'">
            </button>
        </div>
        <div style="display:flex;align-items:center;gap:0;border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;overflow:hidden;background:#faf3fb;">
            <span style="
                padding:8px 10px;
                font-family:'Inter',sans-serif;font-size:11px;color:#7a6884;
                background:rgba(185,92,183,0.06);border-right:1px solid rgba(185,92,183,0.15);
                white-space:nowrap;flex-shrink:0;
            ">dentfluence.in/blog/</span>
            <input
                type="text"
                x-model="blogSlug"
                @input="slugLocked = true"
                style="
                    flex:1;padding:8px 10px;
                    border:none;outline:none;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:transparent;
                "
            >
        </div>
    </div>

    {{-- ── CATEGORY + TAGS ROW ─────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        {{-- Category --}}
        <div>
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">
                Category
            </label>
            <select
                x-model="category"
                style="
                    width:100%;box-sizing:border-box;
                    padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;cursor:pointer;
                "
            >
                <option value="dental-tips">Dental Tips</option>
                <option value="smile-makeover">Smile Makeover</option>
                <option value="oral-health">Oral Health</option>
                <option value="clinic-news">Clinic News</option>
                <option value="patient-stories">Patient Stories</option>
                <option value="technology">Technology</option>
            </select>
        </div>

        {{-- Reading time estimate --}}
        <div style="
            display:flex;flex-direction:column;justify-content:center;
            background:rgba(106,15,112,0.05);border:1px solid rgba(185,92,183,0.14);
            border-radius:6px;padding:8px 12px;text-align:center;
        ">
            <div style="font-family:'Inter',sans-serif;font-size:10px;color:#7a6884;margin-bottom:2px;">Est. Read Time</div>
            <div style="font-family:'Inter',sans-serif;font-size:18px;font-weight:700;color:#6a0f70;">~3 min</div>
        </div>
    </div>

    {{-- ── RICH TEXT EDITOR PLACEHOLDER ────────────────────────────── --}}
    <div style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">
                Post Content
            </label>
            <span style="
                font-family:'Inter',sans-serif;font-size:10px;
                background:#f3e8ff;color:#6a0f70;border-radius:4px;padding:2px 7px;
            ">Rich Text Editor</span>
        </div>

        {{-- Editor toolbar mock --}}
        <div style="
            border:1.5px solid rgba(185,92,183,0.22);border-radius:8px 8px 0 0;
            background:#f9f4fb;padding:6px 10px;
            display:flex;gap:4px;flex-wrap:wrap;align-items:center;
            border-bottom:1px solid rgba(185,92,183,0.12);
        ">
            @foreach(['B','I','U','H1','H2','—','≡','«»','',''] as $tool)
            <button type="button" style="
                min-width:28px;height:26px;padding:0 6px;
                font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#5a4868;
                background:transparent;border:1px solid transparent;border-radius:4px;cursor:pointer;
                transition:background 120ms;
            "
            onmouseover="this.style.background='rgba(106,15,112,0.08)'"
            onmouseout="this.style.background='transparent'"
            >{{ $tool }}</button>
            @endforeach
        </div>

        <textarea
            x-model="blogContent"
            rows="8"
            placeholder="Write your blog post content here…"
            style="
                width:100%;box-sizing:border-box;
                padding:12px;
                border:1.5px solid rgba(185,92,183,0.22);border-top:none;
                border-radius:0 0 8px 8px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;line-height:1.7;
                background:#fff;outline:none;resize:vertical;
            "
        ></textarea>
    </div>

    {{-- ── SEO META DESCRIPTION ────────────────────────────────────── --}}
    <div style="margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">
                Meta Description
                <span style="color:#9b7aaa;font-weight:400;">(SEO)</span>
            </label>
            <span :style="`font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:${metaColor};`">
                <span x-text="metaLength"></span>/160
            </span>
        </div>
        <textarea
            x-model="metaDesc"
            rows="3"
            placeholder="Write a compelling meta description for search engines…"
            style="
                width:100%;box-sizing:border-box;
                padding:9px 11px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;line-height:1.55;
                background:#faf3fb;outline:none;resize:none;
            "
        ></textarea>
        <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-top:3px;">
            Keep under 160 characters for optimal Google display.
        </div>
    </div>

    {{-- ── TAGS ────────────────────────────────────────────────────── --}}
    <div>
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:7px;">
            Post Tags
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
            <template x-for="(tag, i) in tags" :key="i">
                <span style="
                    display:inline-flex;align-items:center;gap:4px;
                    background:rgba(106,15,112,0.08);border:1px solid rgba(106,15,112,0.18);
                    border-radius:20px;padding:3px 10px;
                    font-family:'Inter',sans-serif;font-size:11px;color:#6a0f70;
                ">
                    <span x-text="tag"></span>
                    <button type="button" @click="removeTag(i)" style="background:none;border:none;cursor:pointer;color:#9b3da0;font-size:11px;padding:0;line-height:1;">✕</button>
                </span>
            </template>
        </div>
        <div style="display:flex;gap:6px;">
            <input
                type="text"
                x-model="newTag"
                @keydown.enter.prevent="addTag()"
                placeholder="Add tag…"
                style="
                    flex:1;padding:7px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                "
            >
            <button type="button" @click="addTag()" style="
                padding:7px 14px;
                background:#6a0f70;color:#fff;
                border:none;border-radius:6px;cursor:pointer;
                font-family:'Inter',sans-serif;font-size:12px;font-weight:600;
            ">Add</button>
        </div>
    </div>

</div>
