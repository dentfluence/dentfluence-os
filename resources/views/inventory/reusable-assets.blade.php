@extends('layouts.app')
@section('page-title', 'Inventory')
@section('content')
<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Clinical Resource Operating System</div>
    </div>
</div>
@include('inventory.partials.subnav')
<div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;padding:48px;text-align:center;">
    <div style="font-family:'Cormorant Garamond',serif;font-size:22px;color:#6a0f70;margin-bottom:8px;">Coming in Phase 2</div>
    <div style="font-size:13px;color:#9a85aa;">This section is being built. The database architecture is ready.</div>
</div>
@endsection
