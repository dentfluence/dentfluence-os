@extends('layouts.app')
@section('page-title', 'Knowledge Bank')

@push('styles')
<style>
#df-content-inner { padding: 0 !important; height: 100%; display: flex; flex-direction: column; }

.kb-card { background:#fff; border:1.5px solid #ede4f3; border-radius:12px; overflow:hidden; max-width:760px; margin:0 auto; }
.kb-card + .kb-card { margin-top:20px; }
.kb-card-head { padding:14px 18px; border-bottom:1px solid #ede4f3; }
.kb-card-title { font-size:13px; font-weight:700; color:#1a0320; }

.kb-row { display:flex; align-items:flex-start; gap:10px; padding:12px 18px; border-bottom:1px solid #f9f5fc; flex-wrap:wrap; }
.kb-row:last-child { border-bottom:none; }
.kb-treatment-name { font-size:13px; color:#1a0320; font-weight:600; flex:1 1 160px; padding-top:8px; }
.kb-treatment-cat { display:block; font-size:11px; color:#9a7aaa; font-weight:400; }

.kb-input, .kb-select { padding:6px 9px; border:1.5px solid #e0d4ea; border-radius:7px; font-size:12.5px; color:#1a0320; font-family:inherit; outline:none; background:#fff; }
.kb-input:focus, .kb-select:focus { border-color:#8b44aa; }

.kb-rank { display:inline-block; padding:2px 9px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
.kb-rank--best        { background:#e6f6ec; color:#1a7a45; }
.kb-rank--acceptable   { background:#fef3e0; color:#a05c00; }
.kb-rank--alternative  { background:#e8eefb; color:#1558b0; }

.kb-btn { padding:6px 12px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; border:none; }
.kb-btn--save { background:#6a0f70; color:#fff; }
.kb-btn--add  { background:#6a0f70; color:#fff; padding:8px 16px; }
.kb-btn--remove { background:none; border:none; cursor:pointer; color:#c5a8d8; font-size:18px; line-height:1; padding:4px; }

.kb-empty { padding:18px; color:#b0a0bb; font-size:12.5px; }
</style>
@endpush

@section('content')
<div style="font-family:'Inter',sans-serif;height:100%;display:flex;flex-direction:column;background:#f7f4fa;overflow-y:auto;">

    {{-- ── PAGE HEADER ── --}}
    <div style="padding:18px 28px 16px;background:#fff;border-bottom:1px solid #ede4f3;flex-shrink:0;display:flex;align-items:center;gap:14px;">
        <a href="{{ route('settings.index', ['tab' => 'knowledge-bank']) }}"
           style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1.5px solid #e0d4ea;border-radius:8px;color:#7a6080;text-decoration:none;flex-shrink:0;"
           title="Back to Knowledge Bank">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;color:#1a0320;margin:0 0 2px;">{{ $diagnosis->name }}</h1>
            <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Rank which treatment is recommended, acceptable, or an alternative for this diagnosis.</p>
        </div>
    </div>

    <div style="padding:24px 28px 40px;">

        @if ($errors->any())
        <div class="kb-card" style="border-color:#f3c9c9;margin-bottom:20px;">
            <div style="padding:12px 18px;color:#b52020;font-size:12.5px;">
                {{ $errors->first() }}
            </div>
        </div>
        @endif

        {{-- ── Existing ranked options ── --}}
        <div class="kb-card">
            <div class="kb-card-head">
                <span class="kb-card-title">Ranked Options</span>
            </div>

            @forelse($diagnosis->treatmentOptions as $option)
            <form action="{{ route('settings.knowledge-bank.options.update', $option) }}" method="POST" class="kb-row">
                @csrf @method('PATCH')

                <div class="kb-treatment-name">
                    {{ $option->treatment?->name ?? '(treatment removed)' }}
                    @if($option->treatment?->category)
                    <span class="kb-treatment-cat">{{ $option->treatment->category->name }}</span>
                    @endif
                </div>

                <select name="rank" class="kb-select">
                    @foreach(\App\Models\DiagnosisTreatmentOption::RANKS as $value => $label)
                    <option value="{{ $value }}" {{ $option->rank === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="text" name="notes" class="kb-input" style="flex:1 1 180px;" placeholder="Why this rank (optional)" value="{{ $option->notes }}">

                <input type="number" name="sort_order" class="kb-input" style="width:56px;" min="0" max="999" value="{{ $option->sort_order }}" title="Display order">

                <button type="submit" class="kb-btn kb-btn--save">Save</button>
            </form>
            @empty
            <p class="kb-empty">No treatment options ranked yet for this diagnosis — add one below.</p>
            @endforelse
        </div>

        {{-- ── Delete buttons (separate forms so Save above can't accidentally trigger a delete) ── --}}
        @if($diagnosis->treatmentOptions->isNotEmpty())
        <div class="kb-card" style="margin-top:8px;">
            <div class="kb-card-head">
                <span class="kb-card-title" style="color:#b52020;">Remove an option</span>
            </div>
            @foreach($diagnosis->treatmentOptions as $option)
            <div class="kb-row" style="align-items:center;">
                <span class="kb-treatment-name" style="padding-top:0;">{{ $option->treatment?->name ?? '(treatment removed)' }}</span>
                <span class="kb-rank kb-rank--{{ $option->rank }}">{{ $option->rank_label }}</span>
                <form action="{{ route('settings.knowledge-bank.options.destroy', $option) }}" method="POST" onsubmit="return confirm('Remove this treatment option?')" style="margin-left:auto;">
                    @csrf @method('DELETE')
                    <button type="submit" class="kb-btn--remove" title="Remove">&times;</button>
                </form>
            </div>
            @endforeach
        </div>
        @endif

        {{-- ── Add a new ranked option ── --}}
        @php
            $availableTreatments = $treatments->whereNotIn('id', $diagnosis->treatmentOptions->pluck('treatment_id'));
        @endphp
        <div class="kb-card">
            <div class="kb-card-head">
                <span class="kb-card-title">Add a Treatment Option</span>
            </div>
            @if($availableTreatments->isEmpty())
            <p class="kb-empty">Every active treatment is already ranked for this diagnosis.</p>
            @else
            <form action="{{ route('settings.knowledge-bank.options.store', $diagnosis) }}" method="POST" class="kb-row" style="align-items:center;">
                @csrf

                <select name="treatment_id" class="kb-select" style="flex:1 1 200px;" required>
                    <option value="">Select treatment&hellip;</option>
                    @foreach($availableTreatments as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>

                <select name="rank" class="kb-select" required>
                    @foreach(\App\Models\DiagnosisTreatmentOption::RANKS as $value => $label)
                    <option value="{{ $value }}" {{ $value === 'best' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="text" name="notes" class="kb-input" style="flex:1 1 180px;" placeholder="Why this rank (optional)">

                <button type="submit" class="kb-btn kb-btn--add">Add</button>
            </form>
            @endif
        </div>

    </div>
</div>
@endsection
