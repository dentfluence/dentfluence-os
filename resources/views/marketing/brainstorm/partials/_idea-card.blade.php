{{--
| _idea-card.blade.php — STUB (Phase 2.2-B)
| Variables: $idea (array), $index (int)
| Full card UI will be built in Part B of Phase 2.2-A.
--}}
<div
    @click="openIdea({{ $index }})"
    style="
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.13);
        border-radius: 10px;
        padding: 16px;
        cursor: pointer;
        transition: box-shadow 150ms, border-color 150ms;
    "
    onmouseover="this.style.boxShadow='0 4px 16px rgba(106,15,112,0.10)'; this.style.borderColor='rgba(185,92,183,0.30)'"
    onmouseout="this.style.boxShadow='none'; this.style.borderColor='rgba(185,92,183,0.13)'"
>
    {{-- Image placeholder --}}
    <div style="
        height: 120px;
        background: #f3eaf4;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        position: relative;
    ">
        {{-- Type badge --}}
        <span style="
            position: absolute;
            top: 8px;
            left: 8px;
            height: 20px;
            padding: 0 8px;
            background: rgba(106,15,112,0.85);
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 10.5px;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
        ">{{ $idea['type'] }}</span>

        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(185,92,183,0.30)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
        </svg>
    </div>

    {{-- Title --}}
    <p style="
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #1e0a2c;
        margin: 0 0 5px;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    ">{{ $idea['title'] }}</p>

    {{-- Description --}}
    <p style="
        font-family: 'Inter', sans-serif;
        font-size: 11.5px;
        font-weight: 300;
        color: #7a6884;
        margin: 0 0 10px;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    ">{{ $idea['description'] }}</p>

    {{-- Tag chips --}}
    <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px;">
        @foreach(array_slice($idea['tags'], 0, 2) as $tag)
        <span style="
            height: 20px;
            padding: 0 8px;
            background: #f3eaf4;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 10.5px;
            font-weight: 500;
            color: #6a0f70;
            display: inline-flex;
            align-items: center;
        ">{{ $tag }}</span>
        @endforeach
    </div>

    {{-- Action row --}}
    <div style="display: flex; align-items: center; gap: 6px;">
        {{-- Bookmark --}}
        <button type="button" @click.stop style="
            width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            background: none; border: 1px solid rgba(185,92,183,0.18); border-radius: 5px;
            cursor: pointer; color: #9b6aad;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
            </svg>
        </button>
        {{-- More --}}
        <button type="button" @click.stop style="
            width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            background: none; border: 1px solid rgba(185,92,183,0.18); border-radius: 5px;
            cursor: pointer; color: #9b6aad;
        ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
            </svg>
        </button>

        <div style="flex: 1;"></div>

        {{-- Save --}}
        <button type="button" @click.stop style="
            height: 28px; padding: 0 12px;
            background: linear-gradient(135deg, #6a0f70 0%, #9b3da0 100%);
            border: none; border-radius: 5px;
            font-family: 'Inter', sans-serif; font-size: 11.5px; font-weight: 600;
            color: #fff; cursor: pointer;
            transition: opacity 150ms;
        "
        onmouseover="this.style.opacity='0.88'"
        onmouseout="this.style.opacity='1'"
        >Save</button>
    </div>
</div>
