{{--
| Marketing — WordPress Setup
| File: resources/views/marketing/integrations/wordpress.blade.php
| Phase 5 — App-password form (no OAuth needed for WP REST API)
--}}
@extends('marketing.layouts.app')
@php $marketingPageTitle = 'WordPress Setup'; @endphp
@section('page-title', 'Marketing — WordPress Setup')

@section('marketing-content')

<div style="max-width:560px;">

    <div style="margin-bottom:24px;">
        <a href="{{ route('marketing.integrations') }}" style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Integrations
        </a>
        <h1 class="df-page-title" style="margin-top:12px;">WordPress</h1>
        <p class="df-page-subtitle">Connect your clinic website using a WordPress Application Password. We use the WP REST API to publish posts.</p>
    </div>

    @if(session('error'))
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-family:'Inter',sans-serif;font-size:13.5px;color:#991b1b;">
        {{ session('error') }}
    </div>
    @endif

    {{-- How-to hint --}}
    <div style="background:#faf5ff;border:1px solid rgba(185,92,183,0.2);border-radius:10px;padding:14px 18px;margin-bottom:20px;font-family:'Inter',sans-serif;font-size:13px;color:#5b2d8e;line-height:1.6;">
        <strong>How to get an Application Password:</strong><br>
        In your WordPress admin → Users → Your Profile → scroll to <em>Application Passwords</em> → enter a name (e.g. "Dentfluence") → click Add.
    </div>

    <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:12px;padding:28px;">
        <form method="POST" action="{{ route('marketing.integrations.wordpress-save') }}">
            @csrf

            <div style="margin-bottom:20px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    Site URL <span style="color:#ef4444;">*</span>
                </label>
                <input type="url" name="site_url" required
                    placeholder="https://yourclinic.com"
                    value="{{ $conn ? ($conn->meta['site_url'] ?? $conn->external_account_id) : '' }}"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    WordPress Username <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="username" required
                    placeholder="admin"
                    value="{{ $conn?->meta['username'] ?? '' }}"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:28px;">
                <label style="font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#1e0a2c;display:block;margin-bottom:6px;">
                    Application Password <span style="color:#ef4444;">*</span>
                </label>
                <input type="password" name="app_password" required
                    placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                    style="width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-family:'Inter',sans-serif;font-size:13.5px;color:#1e0a2c;box-sizing:border-box;">
                <p style="font-family:'Inter',sans-serif;font-size:11px;color:#9ca3af;margin:5px 0 0;">We'll verify the connection before saving.</p>
            </div>

            <button type="submit" style="width:100%;background:linear-gradient(135deg,#7a1fa2,#6a0f70);color:#fff;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;font-weight:600;padding:12px;cursor:pointer;">
                {{ $conn ? 'Update Connection' : 'Verify & Connect' }}
            </button>
        </form>
    </div>

</div>
@endsection
