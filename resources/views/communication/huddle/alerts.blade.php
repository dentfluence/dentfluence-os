@extends('layouts.communication')
@section('page-title', 'Communication Alerts')
@section('content')
<div style="padding:28px;font-family:'DM Sans',sans-serif;">
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">Communication Alerts</h1>
    @foreach($alerts as $alert)
    <div style="padding:12px 16px;border:1px solid #ede4f3;border-radius:8px;margin-bottom:10px;">
        {{ $alert['icon'] }} <strong>{{ $alert['title'] }}</strong> ({{ $alert['count'] }})
    </div>
    @endforeach
</div>
@endsection
