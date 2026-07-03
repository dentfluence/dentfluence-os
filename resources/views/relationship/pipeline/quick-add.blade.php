{{--
|==========================================================================
| PRE — Quick Add Lead (Phase 8 · Slice 2 — PRM Retirement)
| Route: GET/POST /relationship/pipeline/quick-add
|
| Dead-simple 4-field staff entry form, ported from
| communication/prm/quick-add.blade.php. Same fields, same validation
| (LeadPipelineController::storeQuickLead), PRE's layout/styling.
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Quick Add Lead')

@section('content')
<div style="max-width:480px;margin:0 auto;padding:24px 16px 60px;">

    <a href="{{ route('relationship.pipeline') }}" style="font-size:12px;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;">
        ← Back to Pipeline
    </a>

    <h1 style="font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;margin:0 0 4px;">New Lead</h1>
    <p style="color:#6b7280;font-size:13px;margin:0 0 22px;">Fill in these 4 details and you're done.</p>

    @if ($errors->any())
        <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#8A1F1F;">
            @foreach ($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (session('success'))
        <div style="background:#EAF3DE;border:1px solid #c3dba0;border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#3B6D11;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('relationship.pipeline.store-quick-lead') }}">
        @csrf

        <div style="margin-bottom:18px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                Patient's Name <span style="color:#c0392b;">*</span>
            </label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Priya Sharma" required autofocus
                   style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
        </div>

        <div style="margin-bottom:18px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                Phone Number <span style="color:#c0392b;">*</span>
            </label>
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="e.g. 9876543210" required
                   style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
        </div>

        <div style="margin-bottom:18px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                How did they find us? <span style="color:#c0392b;">*</span>
            </label>
            <select name="lead_source" required
                    style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                <option value="">— Choose one —</option>
                @foreach ($leadSources as $key => $label)
                    <option value="{{ $key }}" {{ old('lead_source') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom:22px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                What treatment do they want?
            </label>
            <select name="treatment"
                    style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                <option value="">— Don't know yet —</option>
                @foreach ($treatments as $t)
                    <option value="{{ $t }}" {{ old('treatment') === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit"
                style="width:100%;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:13px;font-size:15px;font-weight:600;cursor:pointer;">
            Add to Pipeline
        </button>
    </form>

    <a href="{{ route('relationship.pipeline.add-lead') }}" style="display:block;text-align:center;margin-top:14px;font-size:12px;color:#6b7280;text-decoration:none;">
        Need to add more details? Use the full form →
    </a>
</div>
@endsection
