{{--
|==========================================================================
| _idea-detail-panel.blade.php
| File: resources/views/marketing/brainstorm/partials/_idea-detail-panel.blade.php
|
| Right slide-in detail panel. Rendered once; Alpine drives which idea shows.
|
| Alpine vars (from parent x-data in index.blade.php):
|   panelOpen   : bool — controls visibility
|   activeIdea  : int|null — index into $ideas array
|   closePanel(): fn — hides panel + resets activeIdea
|
| Strategy: emit $ideas as JSON into a JS variable so Alpine can reactively
| display the correct idea's content when activeIdea changes.
|==========================================================================
--}}

{{-- ── Inject PHP ideas into JS once ─────────────────────────────── --}}
<script>
    window._dfIdeas = @json($ideas);
</script>

{{-- ════════════════════════════════════════════════════════════════════
     PANEL SHELL
     Fixed right drawer, z-40 (above backdrop at z-39)
════════════════════════════════════════════════════════════════════ --}}
<div
    x-show="panelOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-x-8"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-8"
    x-init="$watch('activeIdea', val => { if (val !== null) idea = window._dfIdeas[val]; })"
    x-data="{ idea: null, notes: '', get notesLen() { return this.notes.length; } }"
    style="
        position: fixed;
        top: 0;
        right: 0;
        width: 420px;
        height: 100vh;
        background: #ffffff;
        border-left: 1px solid rgba(185,92,183,0.15);
        box-shadow: -4px 0 28px rgba(106,15,112,0.12);
        z-index: 40;
        display: none;
        flex-direction: column;
        overflow: hidden;
    "
>
    {{-- ── SCROLLABLE BODY ─────────────────────────────────────────── --}}
    <div style="flex: 1; overflow-y: auto; padding: 24px 22px 0;">

        {{-- ── HEADER ROW: close X ─────────────────────────────────── --}}
        <div style="display: flex; justify-content: flex-end; margin-bottom: 18px;">
            <button
                type="button"
                @click="closePanel()"
                style="
                    width: 28px; height: 28px;
                    display: flex; align-items: center; justify-content: center;
                    background: #f3eaf4; border: none; border-radius: 50%;
                    cursor: pointer; color: #6a0f70;
                    transition: background 150ms;
                "
                onmouseover="this.style.background='#e8d8ea'"
                onmouseout="this.style.background='#f3eaf4'"
                title="Close"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- ── TITLE + BADGE + EDIT ICON ───────────────────────────── --}}
        <div style="margin-bottom: 20px;">

            {{-- Content type badge --}}
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 9px;">
                <span
                    x-text="idea ? idea.type.charAt(0).toUpperCase() + idea.type.slice(1) : ''"
                    style="
                        display: inline-flex;
                        align-items: center;
                        height: 22px;
                        padding: 0 10px;
                        background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
                        border-radius: 4px;
                        font-family: 'Inter', sans-serif;
                        font-size: 10.5px;
                        font-weight: 700;
                        color: #fff;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    "
                ></span>

                {{-- Platform badge --}}
                <span
                    x-text="idea ? idea.platform : ''"
                    style="
                        display: inline-flex;
                        align-items: center;
                        height: 22px;
                        padding: 0 10px;
                        background: #f3eaf4;
                        border-radius: 4px;
                        font-family: 'Inter', sans-serif;
                        font-size: 10.5px;
                        font-weight: 600;
                        color: #6a0f70;
                        letter-spacing: 0.02em;
                    "
                ></span>
            </div>

            {{-- Title row --}}
            <div style="display: flex; align-items: flex-start; gap: 8px;">
                <h2
                    x-text="idea ? idea.title : ''"
                    style="
                        flex: 1;
                        font-family: 'Cormorant Garamond', serif;
                        font-size: 19px;
                        font-weight: 600;
                        color: #1e0a2c;
                        line-height: 1.3;
                        margin: 0;
                    "
                ></h2>

                {{-- Edit icon --}}
                <button type="button" style="
                    flex-shrink: 0;
                    width: 28px; height: 28px;
                    display: flex; align-items: center; justify-content: center;
                    background: none;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 5px;
                    cursor: pointer;
                    color: #9b6aad;
                    margin-top: 3px;
                    transition: background 150ms;
                "
                onmouseover="this.style.background='#f3eaf4'"
                onmouseout="this.style.background='none'"
                title="Edit title"
                >
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div style="height: 1px; background: rgba(185,92,183,0.10); margin-bottom: 20px;"></div>

        {{-- ── IDEA DESCRIPTION ─────────────────────────────────────── --}}
        <div style="margin-bottom: 22px;">
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 700;
                color: #9b6aad;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                margin: 0 0 9px;
            ">Idea Description</p>
            <p
                x-text="idea ? idea.description : ''"
                style="
                    font-family: 'Inter', sans-serif;
                    font-size: 13px;
                    font-weight: 300;
                    color: #3d2550;
                    line-height: 1.6;
                    margin: 0;
                "
            ></p>
        </div>

        <div style="height: 1px; background: rgba(185,92,183,0.10); margin-bottom: 20px;"></div>

        {{-- ── REFERENCE IMAGES ─────────────────────────────────────── --}}
        <div style="margin-bottom: 22px;">
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 700;
                color: #9b6aad;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                margin: 0 0 11px;
            ">Reference Images</p>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">

                {{-- 3 image placeholder thumbnails --}}
                @foreach([1,2,3] as $thumb)
                <div style="
                    aspect-ratio: 1;
                    background: #f3eaf4;
                    border-radius: 7px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid rgba(185,92,183,0.13);
                    cursor: pointer;
                    transition: border-color 150ms;
                "
                onmouseover="this.style.borderColor='rgba(185,92,183,0.35)'"
                onmouseout="this.style.borderColor='rgba(185,92,183,0.13)'"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(185,92,183,0.30)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                </div>
                @endforeach

                {{-- + Add Images tile --}}
                <div style="
                    aspect-ratio: 1;
                    background: #fff;
                    border-radius: 7px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 4px;
                    border: 1.5px dashed rgba(185,92,183,0.30);
                    cursor: pointer;
                    transition: border-color 150ms, background 150ms;
                "
                onmouseover="this.style.borderColor='#6a0f70'; this.style.background='#f9f3fa'"
                onmouseout="this.style.borderColor='rgba(185,92,183,0.30)'; this.style.background='#fff'"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span style="
                        font-family: 'Inter', sans-serif;
                        font-size: 9.5px;
                        font-weight: 600;
                        color: #9b6aad;
                        text-align: center;
                        line-height: 1.2;
                    ">Add<br>Images</span>
                </div>

            </div>
        </div>

        <div style="height: 1px; background: rgba(185,92,183,0.10); margin-bottom: 20px;"></div>

        {{-- ── KEY POINTS TO COVER ─────────────────────────────────── --}}
        <div style="margin-bottom: 22px;">
            <p style="
                font-family: 'Inter', sans-serif;
                font-size: 11px;
                font-weight: 700;
                color: #9b6aad;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                margin: 0 0 11px;
            ">Key Points to Cover</p>

            {{-- Checklist — generated from the idea's tags + a few fixed items --}}
            <div style="display: flex; flex-direction: column; gap: 9px;">

                {{-- Dynamic tag-based points --}}
                <template x-if="idea && idea.tags">
                    <div style="display: flex; flex-direction: column; gap: 9px;">
                        <template x-for="(tag, i) in idea.tags" :key="i">
                            <div style="display: flex; align-items: flex-start; gap: 9px;">
                                {{-- Green check --}}
                                <div style="
                                    width: 17px; height: 17px; flex-shrink: 0;
                                    background: #e6f9ee;
                                    border: 1.5px solid #34c369;
                                    border-radius: 50%;
                                    display: flex; align-items: center; justify-content: center;
                                    margin-top: 1px;
                                ">
                                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#1ba354" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </div>
                                <span
                                    x-text="'Highlight ' + tag + ' benefits and outcomes'"
                                    style="
                                        font-family: 'Inter', sans-serif;
                                        font-size: 12.5px;
                                        font-weight: 300;
                                        color: #3d2550;
                                        line-height: 1.4;
                                    "
                                ></span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Fixed universal points --}}
                @php
                    $fixedPoints = [
                        'Include a clear call-to-action (Book a consultation)',
                        'Show real results or patient testimonials where possible',
                        'End with clinic name, phone number, and booking link',
                    ];
                @endphp
                @foreach($fixedPoints as $point)
                <div style="display: flex; align-items: flex-start; gap: 9px;">
                    <div style="
                        width: 17px; height: 17px; flex-shrink: 0;
                        background: #e6f9ee;
                        border: 1.5px solid #34c369;
                        border-radius: 50%;
                        display: flex; align-items: center; justify-content: center;
                        margin-top: 1px;
                    ">
                        <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#1ba354" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <span style="
                        font-family: 'Inter', sans-serif;
                        font-size: 12.5px;
                        font-weight: 300;
                        color: #3d2550;
                        line-height: 1.4;
                    ">{{ $point }}</span>
                </div>
                @endforeach

            </div>
        </div>

        <div style="height: 1px; background: rgba(185,92,183,0.10); margin-bottom: 20px;"></div>

        {{-- ── NOTES (OPTIONAL) ────────────────────────────────────── --}}
        <div style="margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 9px;">
                <p style="
                    font-family: 'Inter', sans-serif;
                    font-size: 11px;
                    font-weight: 700;
                    color: #9b6aad;
                    text-transform: uppercase;
                    letter-spacing: 0.07em;
                    margin: 0;
                ">Notes <span style="font-weight:400; text-transform:none; letter-spacing:0;">(Optional)</span></p>

                {{-- Character counter --}}
                <span style="
                    font-family: 'Inter', sans-serif;
                    font-size: 11px;
                    color: #9b6aad;
                ">
                    <span x-text="notesLen">0</span>/500
                </span>
            </div>

            <textarea
                x-model="notes"
                maxlength="500"
                placeholder="Add any additional notes, references, or directions for this idea…"
                style="
                    width: 100%;
                    height: 96px;
                    padding: 10px 12px;
                    background: #f9f3fa;
                    border: 1px solid rgba(185,92,183,0.22);
                    border-radius: 7px;
                    font-family: 'Inter', sans-serif;
                    font-size: 12.5px;
                    font-weight: 300;
                    color: #1e0a2c;
                    line-height: 1.5;
                    resize: none;
                    outline: none;
                    box-sizing: border-box;
                    transition: border-color 150ms;
                "
                onfocus="this.style.borderColor='rgba(106,15,112,0.45)'"
                onblur="this.style.borderColor='rgba(185,92,183,0.22)'"
            ></textarea>
        </div>

    </div>
    {{-- /SCROLLABLE BODY --}}


    {{-- ── STICKY FOOTER ───────────────────────────────────────────── --}}
    <div style="
        flex-shrink: 0;
        display: flex;
        gap: 10px;
        align-items: center;
        padding: 16px 22px;
        border-top: 1px solid rgba(185,92,183,0.13);
        background: #ffffff;
    ">
        {{-- Save as Draft --}}
        <button type="button" style="
            flex: 1;
            height: 38px;
            background: #ffffff;
            border: 1px solid rgba(185,92,183,0.30);
            border-radius: 7px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            color: #6a0f70;
            cursor: pointer;
            transition: background 150ms;
        "
        onmouseover="this.style.background='#f9f3fa'"
        onmouseout="this.style.background='#ffffff'"
        >Save as Draft</button>

        {{-- Convert to Publish --}}
        <button type="button" style="
            flex: 1;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
            border: none;
            border-radius: 7px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            cursor: pointer;
            transition: opacity 150ms;
        "
        onmouseover="this.style.opacity='0.88'"
        onmouseout="this.style.opacity='1'"
        >
            Convert to Publish
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
            </svg>
        </button>
    </div>
    {{-- /STICKY FOOTER --}}

</div>
{{-- /PANEL SHELL --}}
