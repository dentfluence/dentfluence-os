@extends('layouts.communication')
@section('page-title', 'Message Templates')
@section('communication-content')
{{-- Aligned to the Communication OS design system: DM Sans + module tokens
     (was an off-system serif/Inter header with #1a0320). 2026-06-26 --}}
<div style="padding:28px;font-family:var(--comm-font);">
    <h1 style="font-size:22px;font-weight:600;color:var(--c-text-primary);margin:0 0 4px;">Message Templates</h1>
    <p style="color:var(--comm-muted);font-size:13px;margin:0;">Manage WhatsApp, SMS, and Email templates.</p>
    {{-- TODO: templates list --}}
</div>
@endsection
