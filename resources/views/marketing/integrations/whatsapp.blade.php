{{--
| Marketing — WhatsApp Business Setup
| File: resources/views/marketing/integrations/whatsapp.blade.php
| Phase 5 — Static token form (Meta Cloud API / 360Dialog)
--}}
@extends('marketing.layouts.app')
@php $marketingPageTitle = 'WhatsApp Setup'; @endphp
@section('page-title', 'Marketing — WhatsApp Setup')

@section('marketing-content')

<div style="max-width:560px;">

    <div style="margin-bottom:24px;">
        <a href="{{ route('marketing.integrations') }}" style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Integrations
        </a>
        <h1 class="df-page-title" style="margin-top:12px;">WhatsApp Business</h1>
        <p class="df-page-subtitle">Connect via Meta Cloud API or 360Dialog. Paste your access token and Phone Number ID from the Meta Developer portal.</p>
    </div>

    @if(session('error'))
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-family:'Inter',sans-serif;font-size:13.5px;color:#991b1b;">
        {{ session('error') }}
    </div>
    @endif

    <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:12px;padding:28px;">
        <form method="POST" action="{{ route('marketing.integrations.whatsapp-save') }}">
            @csrf

            <div style="margin-bottom:20px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    Permanent Access Token <span style="color:#ef4444;">*</span>
                </label>
                <input type="password" name="access_token" required
                    placeholder="EAAxxxxxxxxxxxxxx…"
                    value="{{ $conn?->access_token ? '••••••••••••' : '' }}"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
                <p style="font-family:'Inter',sans-serif;font-size:11px;color:#9ca3af;margin:5px 0 0;">From Meta Developer → WhatsApp → API Setup → Permanent token</p>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    Phone Number ID <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="phone_number_id" required
                    placeholder="123456789012345"
                    value="{{ $conn?->external_account_id }}"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
                <p style="font-family:'Inter',sans-serif;font-size:11px;color:#9ca3af;margin:5px 0 0;">Numeric ID of the WhatsApp Business phone number</p>
            </div>

            <div style="margin-bottom:28px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    Display Name
                </label>
                <input type="text" name="display_name"
                    placeholder="Dentfluence WhatsApp"
                    value="{{ $conn?->external_account_name }}"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
            </div>

            <button type="submit" style="width:100%;background:linear-gradient(135deg,#7a1fa2,#6a0f70);color:#fff;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;font-weight:600;padding:12px;cursor:pointer;">
                {{ $conn ? 'Update Connection' : 'Save & Connect' }}
            </button>
        </form>
    </div>

</div>
@endsection
