{{--
|==========================================================================
| Universal Publish — Panel 1: WhatsApp-specific fields
| File: resources/views/marketing/publish/partials/_panel1-whatsapp.blade.php
|
| Shows when activeTab === 'whatsapp'
| Sections:
|   • Message Type: Text | Image | Document
|   • Template note / warning
|   • Message body
|   • Media upload placeholder (Image / Document type)
|==========================================================================
--}}

<div
    x-data="{
        msgType: 'text',
        msgTypes: ['text','image','document'],
        content: 'Hello!\n\nWe have an exciting offer for you at Dentfluence.\n\n*Free Dental Consultation* this month!\n\nBook your slot now:\nhttps://dentfluence.in/book\n\nReply *YES* to confirm, or call us at +91-XXXXXXXXXX.',
        fileName: '',
        get charCount() { return this.content.length; }
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
            WhatsApp Message
        </span>
        <span style="
            margin-left:auto;
            display:inline-flex;align-items:center;gap:5px;
            background:#dcfce7;border:1px solid #86efac;border-radius:20px;
            padding:3px 10px;
            font-family:'Inter',sans-serif;font-size:10.5px;font-weight:600;color:#166534;
        ">
            WhatsApp
        </span>
    </div>

    {{-- ── WHATSAPP TEMPLATE WARNING ── --}}
    <div style="
        background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;
        padding:12px 14px;margin-bottom:16px;
        display:flex;gap:10px;align-items:flex-start;
    ">
        <div>
            <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#92400e;margin-bottom:3px;">
                WhatsApp Business API Required
            </div>
            <div style="font-family:'Inter',sans-serif;font-size:11px;color:#78350f;line-height:1.5;">
                Bulk WhatsApp messages require pre-approved templates via the WhatsApp Business API. This content will be submitted as a template draft. Freeform messages are only available for 24-hour conversation windows.
            </div>
        </div>
    </div>

    {{-- ── MESSAGE TYPE PILLS ── --}}
    <div style="margin-bottom:16px;">
        <div style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;margin-bottom:7px;">Message Type</div>
        <div style="display:flex;gap:6px;">
            @foreach(['text'=>'Text','image'=>'Image','document'=>'Document'] as $key=>$label)
            <button
                type="button"
                @click="msgType = '{{ $key }}'"
                :style="`
                    flex:1;padding:7px 6px;text-align:center;
                    font-family:'Inter',sans-serif;font-size:11.5px;font-weight:${msgType==='{{ $key }}'?'600':'400'};
                    color:${msgType==='{{ $key }}'?'#ffffff':'#5a4868'};
                    background:${msgType==='{{ $key }}'?'linear-gradient(135deg,#6a0f70,#9b3da0)':'transparent'};
                    border:1.5px solid ${msgType==='{{ $key }}'?'transparent':'rgba(185,92,183,0.22)'};
                    border-radius:8px;cursor:pointer;transition:all 150ms;
                `"
            >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ── MEDIA UPLOAD (Image / Document type) ── --}}
    <div x-show="msgType !== 'text'" x-transition style="margin-bottom:16px;">
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:6px;">
            <span x-text="msgType === 'image' ? 'Upload Image' : 'Upload Document'"></span>
        </label>
        <div style="
            border:2px dashed rgba(185,92,183,0.30);border-radius:8px;
            padding:24px;text-align:center;background:#faf3fb;cursor:pointer;
        "
        onmouseover="this.style.borderColor='rgba(106,15,112,0.5)'"
        onmouseout="this.style.borderColor='rgba(185,92,183,0.30)'"
        >
            <div style="font-size:24px;margin-bottom:6px;" x-text="msgType === 'image' ? '' : ''"></div>
            <div style="font-family:'Inter',sans-serif;font-size:12px;color:#5a4868;margin-bottom:4px;">
                Drag & drop or <span style="color:#6a0f70;font-weight:600;cursor:pointer;">browse</span>
            </div>
            <div style="font-family:'Inter',sans-serif;font-size:10.5px;color:#9b7aaa;">
                <span x-show="msgType === 'image'">JPG, PNG up to 5MB</span>
                <span x-show="msgType === 'document'">PDF, DOCX up to 100MB</span>
            </div>
        </div>
    </div>

    {{-- ── MESSAGE BODY ── --}}
    <div style="margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">
                Message Body
            </label>
            <span style="font-family:'Inter',sans-serif;font-size:10.5px;color:#9b7aaa;">
                <span x-text="charCount"></span> chars
            </span>
        </div>
        <textarea
            x-model="content"
            rows="9"
            placeholder="Write your WhatsApp message… Use *bold*, _italic_, ~strikethrough~"
            style="
                width:100%;box-sizing:border-box;padding:10px 12px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:8px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;line-height:1.6;
                background:#faf3fb;outline:none;resize:vertical;
            "
        ></textarea>
        <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-top:3px;">
            Use *text* for bold, _text_ for italic, ~text~ for strikethrough.
        </div>
    </div>

    {{-- ── TEMPLATE NAME FIELD ── --}}
    <div>
        <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">
            Template Name <span style="color:#9b7aaa;font-weight:400;">(for API submission)</span>
        </label>
        <input
            type="text"
            placeholder="e.g. dentfluence_promo_june_2026"
            style="
                width:100%;box-sizing:border-box;padding:8px 10px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                background:#faf3fb;outline:none;
            "
        >
        <div style="font-family:'Inter',sans-serif;font-size:10px;color:#9b7aaa;margin-top:3px;">
            Lowercase letters, numbers and underscores only. Max 512 characters.
        </div>
    </div>

</div>
