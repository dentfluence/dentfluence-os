{{--
|==========================================================================
| Universal Publish — Panel 2: Platform Previews
| File: resources/views/marketing/publish/partials/_platform-previews.blade.php
|
| Platform cards (in order):
|   • Instagram Feed
|   • Facebook Feed
|   • Google Business
|   • Blog Preview
|   • WhatsApp
|
| Each card has a "..." menu (Edit this version / Reset to master).
| Alpine.js handles the dropdown menus and "Show all platforms" toggle.
|==========================================================================
--}}

<div
    x-data="{
        showAll: true,
        openMenu: null,
        toggleMenu(platform) {
            this.openMenu = this.openMenu === platform ? null : platform;
        },
        closeMenus() { this.openMenu = null; }
    }"
    @click.away="closeMenus()"
    style="padding: 24px 22px;"
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
        ">②</span>
        <span style="
            font-family: 'Inter', sans-serif;
            font-size: 13px; font-weight: 600;
            color: #1e0a2c;
        ">Platform Previews</span>

        {{-- Show all platforms toggle --}}
        <div style="
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 400;
            color: #5a4868;
        ">
            Show all
            <button
                type="button"
                @click="showAll = !showAll"
                :style="`
                    width: 34px; height: 18px;
                    border-radius: 9px;
                    border: none;
                    background: ${showAll ? '#6a0f70' : 'rgba(185,92,183,0.25)'};
                    cursor: pointer;
                    position: relative;
                    transition: background 200ms;
                    flex-shrink: 0;
                `"
            >
                <span :style="`
                    position: absolute;
                    top: 2px;
                    left: ${showAll ? '16px' : '2px'};
                    width: 14px; height: 14px;
                    border-radius: 50%;
                    background: #fff;
                    transition: left 200ms;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
                `"></span>
            </button>
        </div>
    </div>
    {{-- /SECTION LABEL --}}

    {{-- ═══════════════════════════════════════════════════════════
         INSTAGRAM FEED CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="showAll" style="
        background: #ffffff;
        border: 1px solid #e0d0e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    ">
        {{-- Card header --}}
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px 6px;
            border-bottom: 1px solid rgba(185,92,183,0.08);
        ">
            <div style="display:flex;align-items:center;gap:7px;">
                {{-- Platform badge --}}
                <span style="
                    font-size:13px;
                    display:flex;align-items:center;
                "></span>
                <span style="
                    font-family: 'Inter', sans-serif;
                    font-size: 11px;
                    font-weight: 600;
                    color: #C13584;
                    letter-spacing: 0.02em;
                ">Instagram</span>
            </div>
            {{-- "..." menu --}}
            <div style="position:relative;">
                <button
                    type="button"
                    @click.stop="toggleMenu('instagram')"
                    style="
                        background: transparent;
                        border: none;
                        cursor: pointer;
                        color: #9b6aad;
                        padding: 2px 4px;
                        border-radius: 4px;
                        font-size:16px;
                        line-height:1;
                        transition: background 150ms;
                    "
                    onmouseover="this.style.background='rgba(185,92,183,0.08)'"
                    onmouseout="this.style.background='transparent'"
                >⋯</button>
                <div x-show="openMenu === 'instagram'" x-transition style="
                    position: absolute;
                    right: 0; top: 28px;
                    background: #fff;
                    border: 1px solid rgba(185,92,183,0.18);
                    border-radius: 6px;
                    box-shadow: 0 4px 14px rgba(106,15,112,0.12);
                    min-width: 160px;
                    z-index: 50;
                    overflow: hidden;
                ">
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">✏️ Edit this version</button>
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#5a4868;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">↩ Reset to master</button>
                </div>
            </div>
        </div>

        {{-- Preview content --}}
        <div style="padding: 10px 12px 12px;">
            {{-- Profile row --}}
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <div style="
                    width: 30px; height: 30px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
                    display: flex; align-items: center; justify-content: center;
                    font-size: 13px;
                    flex-shrink: 0;
                "></div>
                <div>
                    <div style="font-family:'Inter',sans-serif;font-size:12px;font-weight:600;color:#1e0a2c;line-height:1.2;">dentfluence.clinic</div>
                    <div style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:300;color:#7a6884;">Sponsored</div>
                </div>
                <button type="button" style="margin-left:auto;background:transparent;border:none;cursor:pointer;color:#9b6aad;font-size:18px;line-height:1;">⋯</button>
            </div>

            {{-- Image placeholder --}}
            <div style="
                width: 100%;
                height: 180px;
                background: linear-gradient(135deg, #d4b8e0 0%, #b8d4e0 50%, #e0d4b8 100%);
                border-radius: 4px;
                display: flex; align-items: center; justify-content: center;
                font-size: 40px;
                margin-bottom: 8px;
            "></div>

            {{-- Slide indicators --}}
            <div style="display:flex;justify-content:center;gap:4px;margin-bottom:8px;">
                <span style="width:16px;height:3px;border-radius:2px;background:#6a0f70;"></span>
                <span style="width:5px;height:3px;border-radius:2px;background:rgba(185,92,183,0.3);"></span>
                <span style="width:5px;height:3px;border-radius:2px;background:rgba(185,92,183,0.3);"></span>
                <span style="width:5px;height:3px;border-radius:2px;background:rgba(185,92,183,0.3);"></span>
                <span style="width:5px;height:3px;border-radius:2px;background:rgba(185,92,183,0.3);"></span>
                <span style="
                    font-family:'Inter',sans-serif;
                    font-size:10px;color:#7a6884;
                    margin-left:4px;
                ">1/5</span>
            </div>

            {{-- Action icons --}}
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e0a2c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="cursor:pointer;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </div>

            {{-- Caption --}}
            <div style="
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                color: #1e0a2c;
                line-height: 1.5;
            ">
                <span style="font-weight:600;">dentfluence.clinic</span>
                &nbsp;A confident smile can change everything. ✨ At Dentfluence, we believe every patient deserves...
                <span style="color:#7a6884;cursor:pointer;">more</span>
            </div>
        </div>
    </div>
    {{-- /INSTAGRAM --}}


    {{-- ═══════════════════════════════════════════════════════════
         FACEBOOK FEED CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div style="
        background: #ffffff;
        border: 1px solid #e0d0e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    ">
        {{-- Card header --}}
        <div style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px 6px;
            border-bottom: 1px solid rgba(185,92,183,0.08);
        ">
            <div style="display:flex;align-items:center;gap:7px;">
                <span style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1877F2;letter-spacing:0.02em;">Facebook</span>
            </div>
            <div style="position:relative;">
                <button type="button" @click.stop="toggleMenu('facebook')"
                    style="background:transparent;border:none;cursor:pointer;color:#9b6aad;padding:2px 4px;border-radius:4px;font-size:16px;line-height:1;transition:background 150ms;"
                    onmouseover="this.style.background='rgba(185,92,183,0.08)'" onmouseout="this.style.background='transparent'">⋯</button>
                <div x-show="openMenu === 'facebook'" x-transition style="position:absolute;right:0;top:28px;background:#fff;border:1px solid rgba(185,92,183,0.18);border-radius:6px;box-shadow:0 4px 14px rgba(106,15,112,0.12);min-width:160px;z-index:50;overflow:hidden;">
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">✏️ Edit this version</button>
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#5a4868;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">↩ Reset to master</button>
                </div>
            </div>
        </div>

        {{-- Preview content --}}
        <div style="padding: 10px 12px 12px;">
            {{-- Profile row --}}
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <div style="
                    width: 36px; height: 36px;
                    border-radius: 50%;
                    background: linear-gradient(135deg,#6a0f70,#b95cb7);
                    display:flex;align-items:center;justify-content:center;
                    font-size:14px;flex-shrink:0;
                "></div>
                <div>
                    <div style="font-family:'Inter',sans-serif;font-size:12.5px;font-weight:600;color:#1e0a2c;line-height:1.2;">Dentfluence Clinic</div>
                    <div style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:300;color:#7a6884;">Just now ·</div>
                </div>
                <button type="button" style="margin-left:auto;background:transparent;border:none;cursor:pointer;color:#9b6aad;font-size:18px;">⋯</button>
            </div>

            {{-- Caption --}}
            <div style="
                font-family:'Inter',sans-serif;
                font-size:13px;
                color:#1e0a2c;
                line-height:1.55;
                margin-bottom:8px;
            ">
                A confident smile can change everything. ✨ At Dentfluence, we believe every patient deserves to walk out feeling transformed.
                <span style="color:#7a6884;cursor:pointer;font-size:12px;">See more</span>
            </div>

            {{-- Image --}}
            <div style="
                width:100%;height:150px;
                background: linear-gradient(135deg,#d4b8e0 0%,#b8d4e0 50%,#e0d4b8 100%);
                border-radius:4px;
                display:flex;align-items:center;justify-content:center;
                font-size:38px;
                margin-bottom:10px;
            "></div>

            {{-- Reaction bar --}}
            <div style="
                display:flex;
                gap:0;
                border-top:1px solid rgba(185,92,183,0.10);
                padding-top:8px;
            ">
                @foreach([['','Like'],['','Comment'],['↗','Share']] as $action)
                <button type="button" style="
                    flex:1;
                    display:flex;align-items:center;justify-content:center;gap:5px;
                    background:transparent;border:none;cursor:pointer;
                    font-family:'Inter',sans-serif;font-size:12.5px;font-weight:500;
                    color:#5a4868;
                    padding:6px 0;
                    border-radius:4px;
                    transition:background 150ms;
                "
                onmouseover="this.style.background='rgba(185,92,183,0.06)'"
                onmouseout="this.style.background='transparent'"
                >
                    <span>{{ $action[0] }}</span>
                    <span>{{ $action[1] }}</span>
                </button>
                @endforeach
            </div>
        </div>
    </div>
    {{-- /FACEBOOK --}}


    {{-- ═══════════════════════════════════════════════════════════
         GOOGLE BUSINESS CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div style="
        background: #ffffff;
        border: 1px solid #e0d0e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    ">
        <div style="
            display:flex;align-items:center;justify-content:space-between;
            padding:8px 12px 6px;
            border-bottom:1px solid rgba(185,92,183,0.08);
        ">
            <div style="display:flex;align-items:center;gap:7px;">
                <span style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#4285F4;letter-spacing:0.02em;">Google Business</span>
            </div>
            <div style="position:relative;">
                <button type="button" @click.stop="toggleMenu('google')"
                    style="background:transparent;border:none;cursor:pointer;color:#9b6aad;padding:2px 4px;border-radius:4px;font-size:16px;line-height:1;transition:background 150ms;"
                    onmouseover="this.style.background='rgba(185,92,183,0.08)'" onmouseout="this.style.background='transparent'">⋯</button>
                <div x-show="openMenu === 'google'" x-transition style="position:absolute;right:0;top:28px;background:#fff;border:1px solid rgba(185,92,183,0.18);border-radius:6px;box-shadow:0 4px 14px rgba(106,15,112,0.12);min-width:160px;z-index:50;overflow:hidden;">
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">✏️ Edit this version</button>
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#5a4868;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">↩ Reset to master</button>
                </div>
            </div>
        </div>

        <div style="padding:10px 12px 12px;">
            {{-- Business row --}}
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <div style="
                    width:36px;height:36px;border-radius:8px;
                    background:linear-gradient(135deg,#6a0f70,#b95cb7);
                    display:flex;align-items:center;justify-content:center;
                    font-size:16px;flex-shrink:0;
                "></div>
                <div style="flex:1;">
                    <div style="font-family:'Inter',sans-serif;font-size:12.5px;font-weight:600;color:#1e0a2c;line-height:1.2;">Dentfluence Clinic</div>
                    <div style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:300;color:#4285F4;">Dental Clinic · Mumbai</div>
                </div>
                <span style="
                    display:inline-flex;align-items:center;gap:4px;
                    background:#e8f0fe;
                    border:1px solid #4285F4;
                    border-radius:12px;
                    padding:2px 8px;
                    font-family:'Inter',sans-serif;
                    font-size:10.5px;font-weight:500;
                    color:#4285F4;
                    cursor:pointer;
                ">See more ↗</span>
            </div>

            {{-- Image --}}
            <div style="
                width:100%;height:130px;
                background:linear-gradient(135deg,#d4b8e0 0%,#b8d4e0 50%,#e0d4b8 100%);
                border-radius:6px;
                display:flex;align-items:center;justify-content:center;
                font-size:36px;
                margin-bottom:8px;
            "></div>

            {{-- Caption --}}
            <div style="
                font-family:'Inter',sans-serif;font-size:12.5px;
                color:#1e0a2c;line-height:1.5;
                margin-bottom:10px;
            ">
                A confident smile can change everything. At Dentfluence, we believe every patient deserves...
                <span style="color:#4285F4;cursor:pointer;">See more</span>
            </div>

            {{-- Learn more button --}}
            <button type="button" style="
                display:inline-flex;align-items:center;gap:6px;
                height:34px;padding:0 16px;
                background:#4285F4;
                border:none;border-radius:4px;
                font-family:'Inter',sans-serif;font-size:12.5px;font-weight:500;
                color:#fff;cursor:pointer;
                transition:opacity 150ms;
            " onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
                Learn more
            </button>
        </div>
    </div>
    {{-- /GOOGLE BUSINESS --}}


    {{-- ═══════════════════════════════════════════════════════════
         BLOG PREVIEW CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div style="
        background: #ffffff;
        border: 1px solid #e0d0e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    ">
        <div style="
            display:flex;align-items:center;justify-content:space-between;
            padding:8px 12px 6px;
            border-bottom:1px solid rgba(185,92,183,0.08);
        ">
            <div style="display:flex;align-items:center;gap:7px;">
                <span style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#1e0a2c;letter-spacing:0.02em;">Blog</span>
            </div>
            <div style="position:relative;">
                <button type="button" @click.stop="toggleMenu('blog')"
                    style="background:transparent;border:none;cursor:pointer;color:#9b6aad;padding:2px 4px;border-radius:4px;font-size:16px;line-height:1;transition:background 150ms;"
                    onmouseover="this.style.background='rgba(185,92,183,0.08)'" onmouseout="this.style.background='transparent'">⋯</button>
                <div x-show="openMenu === 'blog'" x-transition style="position:absolute;right:0;top:28px;background:#fff;border:1px solid rgba(185,92,183,0.18);border-radius:6px;box-shadow:0 4px 14px rgba(106,15,112,0.12);min-width:160px;z-index:50;overflow:hidden;">
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">✏️ Edit this version</button>
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#5a4868;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">↩ Reset to master</button>
                </div>
            </div>
        </div>

        <div style="padding:10px 12px 14px;">
            {{-- Hero image --}}
            <div style="
                width:100%;height:140px;
                background:linear-gradient(135deg,#6a0f70 0%,#b95cb7 60%,#d4b8e0 100%);
                border-radius:6px;
                display:flex;align-items:center;justify-content:center;
                font-size:40px;
                margin-bottom:12px;
                position:relative;
                overflow:hidden;
            ">
               
                <div style="
                    position:absolute;bottom:0;left:0;right:0;
                    background:linear-gradient(to top,rgba(30,10,44,0.7),transparent);
                    height:50px;
                "></div>
            </div>

            {{-- Category + read time --}}
            <div style="
                display:flex;align-items:center;gap:8px;
                margin-bottom:6px;
            ">
                <span style="
                    background:rgba(185,92,183,0.10);
                    border-radius:10px;
                    padding:2px 8px;
                    font-family:'Inter',sans-serif;font-size:10.5px;
                    font-weight:500;color:#6a0f70;
                ">Dental Health</span>
                <span style="font-family:'Inter',sans-serif;font-size:10.5px;color:#7a6884;">· 3 min read</span>
            </div>

            {{-- Title --}}
            <div style="
                font-family:'Cormorant Garamond',serif;
                font-size:17px;font-weight:700;
                color:#1e0a2c;line-height:1.3;
                margin-bottom:6px;
            ">A Confident Smile Can Change Everything</div>

            {{-- Excerpt --}}
            <div style="
                font-family:'Inter',sans-serif;font-size:12.5px;
                font-weight:300;color:#5a4868;line-height:1.55;
                margin-bottom:12px;
            ">
                At Dentfluence, we believe every patient deserves to walk out feeling transformed. Whether it's a routine clean or a complete smile makeover...
            </div>

            {{-- Author + date + read more --}}
            <div style="
                display:flex;align-items:center;gap:8px;
            ">
                <div style="
                    width:24px;height:24px;
                    border-radius:50%;
                    background:linear-gradient(135deg,#6a0f70,#b95cb7);
                    display:flex;align-items:center;justify-content:center;
                    font-size:10px;color:#fff;font-weight:600;
                    flex-shrink:0;
                ">Dr</div>
                <div>
                    <div style="font-family:'Inter',sans-serif;font-size:11.5px;font-weight:500;color:#1e0a2c;line-height:1.2;">Dr. Arjun Mehra</div>
                    <div style="font-family:'Inter',sans-serif;font-size:10.5px;font-weight:300;color:#7a6884;">14 Jun 2026</div>
                </div>
                <a href="#" style="
                    margin-left:auto;
                    font-family:'Inter',sans-serif;font-size:12.5px;
                    font-weight:500;color:#6a0f70;
                    text-decoration:none;
                " onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    Read more →
                </a>
            </div>
        </div>
    </div>
    {{-- /BLOG --}}


    {{-- ═══════════════════════════════════════════════════════════
         WHATSAPP CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div style="
        background: #ffffff;
        border: 1px solid #e0d0e8;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 4px;
        box-shadow: 0 1px 4px rgba(106,15,112,0.07);
    ">
        <div style="
            display:flex;align-items:center;justify-content:space-between;
            padding:8px 12px 6px;
            border-bottom:1px solid rgba(185,92,183,0.08);
        ">
            <div style="display:flex;align-items:center;gap:7px;">
                <span style="font-family:'Inter',sans-serif;font-size:11px;font-weight:600;color:#25D366;letter-spacing:0.02em;">WhatsApp</span>
            </div>
            <div style="position:relative;">
                <button type="button" @click.stop="toggleMenu('whatsapp')"
                    style="background:transparent;border:none;cursor:pointer;color:#9b6aad;padding:2px 4px;border-radius:4px;font-size:16px;line-height:1;transition:background 150ms;"
                    onmouseover="this.style.background='rgba(185,92,183,0.08)'" onmouseout="this.style.background='transparent'">⋯</button>
                <div x-show="openMenu === 'whatsapp'" x-transition style="position:absolute;right:0;top:28px;background:#fff;border:1px solid rgba(185,92,183,0.18);border-radius:6px;box-shadow:0 4px 14px rgba(106,15,112,0.12);min-width:160px;z-index:50;overflow:hidden;">
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#1e0a2c;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">✏️ Edit this version</button>
                    <button type="button" style="display:block;width:100%;text-align:left;padding:9px 14px;font-family:'Inter',sans-serif;font-size:12.5px;color:#5a4868;background:transparent;border:none;cursor:pointer;" onmouseover="this.style.background='#faf3fb'" onmouseout="this.style.background='transparent'">↩ Reset to master</button>
                </div>
            </div>
        </div>

        {{-- Chat background --}}
        <div style="
            background: #ECE5DD;
            padding: 16px 12px;
            min-height: 130px;
        ">
            {{-- Chat bubble --}}
            <div style="
                max-width: 88%;
                background: #DCF8C6;
                border-radius: 0 10px 10px 10px;
                padding: 10px 12px 8px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.12);
                position: relative;
            ">
                {{-- Sender name --}}
                <div style="
                    font-family:'Inter',sans-serif;
                    font-size:11.5px;font-weight:600;
                    color:#25D366;
                    margin-bottom:4px;
                ">Dentfluence Clinic</div>

                {{-- Message text --}}
                <div style="
                    font-family:'Inter',sans-serif;
                    font-size:13px;font-weight:300;
                    color:#1e0a2c;line-height:1.55;
                ">
                    <em>A confident smile can change everything!</em><br><br>
                    At Dentfluence, we believe every patient deserves to walk out feeling transformed. Book your consultation today!<br><br>
                    Advanced Technology<br>
                    Gentle &amp; Caring Team<br>
                    Flexible Appointments<br><br>
                    dentfluence.in/book
                </div>

                {{-- Time + read ticks --}}
                <div style="
                    display:flex;align-items:center;justify-content:flex-end;gap:4px;
                    margin-top:6px;
                ">
                    <span style="
                        font-family:'Inter',sans-serif;
                        font-size:10.5px;
                        color:#7a9984;
                    ">11:30 AM</span>
                    {{-- Double blue tick --}}
                    <svg width="16" height="10" viewBox="0 0 16 10" fill="none">
                        <path d="M1 5l3 3 5-6" stroke="#53bdeb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M6 5l3 3 5-6" stroke="#53bdeb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                {{-- Bubble tail --}}
                <div style="
                    position:absolute;
                    top:0;left:-7px;
                    width:0;height:0;
                    border-style:solid;
                    border-width:0 8px 8px 0;
                    border-color:transparent #DCF8C6 transparent transparent;
                "></div>
            </div>
        </div>
    </div>
    {{-- /WHATSAPP --}}

</div>{{-- /x-data platform previews --}}
