{{--
|==========================================================================
| Universal Publish — Panel 1: Google Business Profile fields
| File: resources/views/marketing/publish/partials/_panel1-google.blade.php
|
| Shows when activeTab === 'google_business'
| Sections:
|   • Post type: Update | Offer | Event
|   • Content textarea
|   • Offer Type field (when Offer selected)
|   • Event Start / End (when Event selected)
|   • CTA Button type dropdown
|==========================================================================
--}}

<div
    x-data="{
        gbpType: 'update',
        content: 'A confident smile can change everything. ✨\n\nAt Dentfluence, we believe every patient deserves personalised care. Book your consultation today and discover how we can transform your smile.\n\nVisit us in clinic or call to schedule.',
        offerTitle: '',
        offerCode: '',
        eventTitle: '',
        eventStart: '',
        eventEnd: '',
        ctaType: 'BOOK',
        ctaUrl: 'https://dentfluence.in/book',
        get charCount() { return this.content.length; },
        get charColor() {
            const pct = this.content.length / 1500;
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
            Google Business Post
        </span>
        <span style="
            margin-left:auto;
            display:inline-flex;align-items:center;gap:5px;
            background:#e0f2fe;border:1px solid #7dd3fc;border-radius:20px;
            padding:3px 10px;
            font-family:'Inter',sans-serif;font-size:10.5px;font-weight:600;color:#0369a1;
        ">
            Google Business
        </span>
    </div>

    {{-- ── POST TYPE PILLS ── --}}
    <div style="margin-bottom:16px;">
        <div style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;margin-bottom:7px;">Post Type</div>
        <div style="display:flex;gap:6px;">
            @foreach(['update'=>'Update','offer'=>'Offer','event'=>'Event'] as $key=>$label)
            <button
                type="button"
                @click="gbpType = '{{ $key }}'"
                :style="`
                    flex:1;padding:7px 8px;text-align:center;
                    font-family:'Inter',sans-serif;font-size:11.5px;font-weight:${gbpType==='{{ $key }}'?'600':'400'};
                    color:${gbpType==='{{ $key }}'?'#ffffff':'#5a4868'};
                    background:${gbpType==='{{ $key }}'?'linear-gradient(135deg,#6a0f70,#9b3da0)':'transparent'};
                    border:1.5px solid ${gbpType==='{{ $key }}'?'transparent':'rgba(185,92,183,0.22)'};
                    border-radius:8px;cursor:pointer;transition:all 150ms;
                `"
            >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ── CONTENT TEXTAREA ── --}}
    <div style="margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;">Post Text</label>
            <span :style="`font-family:'Inter',sans-serif;font-size:10.5px;font-weight:500;color:${charColor};`">
                <span x-text="charCount"></span>/1500
            </span>
        </div>
        <textarea
            x-model="content"
            rows="6"
            placeholder="Write your Google Business update…"
            style="
                width:100%;box-sizing:border-box;padding:10px 12px;
                border:1.5px solid rgba(185,92,183,0.22);border-radius:8px;
                font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;line-height:1.6;
                background:#faf3fb;outline:none;resize:vertical;
            "
        ></textarea>
    </div>

    {{-- ── OFFER FIELDS (only when Offer selected) ── --}}
    <div x-show="gbpType === 'offer'" x-transition style="margin-bottom:16px;">
        <div style="background:#fef9ec;border:1px solid #fcd34d;border-radius:8px;padding:14px;">
            <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#92400e;margin-bottom:10px;">Offer Details</div>
            <div style="margin-bottom:10px;">
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">Offer Title</label>
                <input type="text" x-model="offerTitle" placeholder="e.g. 20% off teeth whitening this June" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1px solid rgba(252,211,77,0.5);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;background:#fffbeb;outline:none;
                ">
            </div>
            <div>
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">Coupon Code <span style="color:#9b7aaa;font-weight:400;">(optional)</span></label>
                <input type="text" x-model="offerCode" placeholder="e.g. SMILE20" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1px solid rgba(252,211,77,0.5);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;background:#fffbeb;outline:none;
                    text-transform:uppercase;letter-spacing:0.05em;
                ">
            </div>
        </div>
    </div>

    {{-- ── EVENT FIELDS (only when Event selected) ── --}}
    <div x-show="gbpType === 'event'" x-transition style="margin-bottom:16px;">
        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px;">
            <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;color:#166534;margin-bottom:10px;">Event Details</div>
            <div style="margin-bottom:10px;">
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">Event Title</label>
                <input type="text" x-model="eventTitle" placeholder="e.g. Free Dental Checkup Camp" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1px solid rgba(134,239,172,0.5);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;background:#f0fdf4;outline:none;
                ">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div>
                    <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">Start Date</label>
                    <input type="date" x-model="eventStart" style="
                        width:100%;box-sizing:border-box;padding:8px 10px;
                        border:1px solid rgba(134,239,172,0.5);border-radius:6px;
                        font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;background:#f0fdf4;outline:none;
                    ">
                </div>
                <div>
                    <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:4px;">End Date</label>
                    <input type="date" x-model="eventEnd" style="
                        width:100%;box-sizing:border-box;padding:8px 10px;
                        border:1px solid rgba(134,239,172,0.5);border-radius:6px;
                        font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;background:#f0fdf4;outline:none;
                    ">
                </div>
            </div>
        </div>
    </div>

    {{-- ── CTA BUTTON TYPE ── --}}
    <div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">CTA Button Type</label>
                <select x-model="ctaType" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;cursor:pointer;
                ">
                    <option value="BOOK">Book</option>
                    <option value="ORDER">Order Online</option>
                    <option value="SHOP">Shop</option>
                    <option value="LEARN_MORE">Learn More</option>
                    <option value="SIGN_UP">Sign Up</option>
                    <option value="CALL">Call Now</option>
                </select>
            </div>
            <div>
                <label style="font-family:'Inter',sans-serif;font-size:11px;font-weight:500;color:#5a4868;display:block;margin-bottom:5px;">CTA URL</label>
                <input type="url" x-model="ctaUrl" placeholder="https://…" style="
                    width:100%;box-sizing:border-box;padding:8px 10px;
                    border:1.5px solid rgba(185,92,183,0.22);border-radius:6px;
                    font-family:'Inter',sans-serif;font-size:12px;color:#1e0a2c;
                    background:#faf3fb;outline:none;
                ">
            </div>
        </div>
    </div>

</div>
