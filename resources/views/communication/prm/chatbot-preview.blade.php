@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Website Chatbot — PRM')

@section('communication-content')

@php
    $clinic     = config('prm.replies.clinic_name') ?: config('app.name', 'Your Clinic');
    $greeting   = config('prm.chatbot.greeting');
    $treatments = implode(',', config('prm.treatments', []));
    $endpoint   = url('/api/webhooks/prm/chatbot');
    $widgetSrc  = asset('js/prm-chatbot.js');
    $snippet    = '<script src="' . $widgetSrc . '"' . "\n"
        . '        data-endpoint="' . $endpoint . '"' . "\n"
        . '        data-clinic="' . e($clinic) . '"' . "\n"
        . '        data-greeting="' . e($greeting) . '"' . "\n"
        . '        data-treatments="' . e($treatments) . '">' . "\n"
        . '</' . 'script>';
@endphp

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;">
    <a href="{{ route('prm.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Pipeline
    </a>
</div>

<div style="padding:28px 28px 80px;max-width:760px;">
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">
        Website Chatbot
    </h1>
    <p style="color:#9a7aaa;font-size:13px;margin:0 0 24px;">
        A 24/7 chat widget for your website. It greets visitors, qualifies them, and drops a lead
        straight into your pipeline — where it's auto-assigned, gets a follow-up, and is AI-enriched.
    </p>

    <div style="background:#fff;border:1px solid #eee;border-radius:12px;padding:20px;margin-bottom:20px;">
        <div style="font-weight:600;color:#1a0320;margin-bottom:6px;">Try it now</div>
        <p style="font-size:13px;color:#5A5A56;margin:0;">
            The live widget is loaded on this page — look for the <strong>💬 bubble</strong> at the
            bottom-right. Complete a chat and a real lead will appear in your pipeline.
        </p>
    </div>

    <div style="background:#fff;border:1px solid #eee;border-radius:12px;padding:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="font-weight:600;color:#1a0320;">Install on your website</div>
            <button onclick="copyCbSnippet()" class="btn-outline-sm"><i class="ti ti-copy"></i> Copy</button>
        </div>
        <p style="font-size:12px;color:#9a7aaa;margin:0 0 10px;">
            Paste this just before the closing <code>&lt;/body&gt;</code> tag on your website.
            (For a live site, swap the URLs for your public domain.)
        </p>
        <pre id="cbSnippet" style="background:#1a0320;color:#e7e0ee;padding:14px;border-radius:10px;overflow:auto;font-size:12px;line-height:1.5;white-space:pre;">{{ $snippet }}</pre>
    </div>
</div>

{{-- Live widget on this page, pointed at the local endpoint --}}
<script src="{{ $widgetSrc }}"
        data-endpoint="{{ $endpoint }}"
        data-clinic="{{ $clinic }}"
        data-greeting="{{ $greeting }}"
        data-treatments="{{ $treatments }}"></script>

<script>
function copyCbSnippet() {
    var t = document.getElementById('cbSnippet').textContent;
    navigator.clipboard.writeText(t).then(function(){
        var b = event.target.closest('button'); var o = b.innerHTML;
        b.innerHTML = '<i class="ti ti-check"></i> Copied'; setTimeout(function(){ b.innerHTML = o; }, 1500);
    });
}
</script>

@endsection
