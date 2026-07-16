@extends('hq.layout')
@section('title', 'Dashboard')
@section('content')

<div class="cards">
  <div class="card"><div class="num">{{ $activeClinics }}</div><div class="lbl">Active clinics</div></div>
  <div class="card"><div class="num">{{ $liveSubCount }}</div><div class="lbl">Live subscriptions</div></div>
  <div class="card"><div class="num">₹{{ number_format($mrr) }}</div><div class="lbl">MRR (monthly-equivalent)</div></div>
  <div class="card"><div class="num">{{ $openTickets }}</div><div class="lbl">Open tickets</div></div>
</div>

@if($lapsed->isNotEmpty())
<div class="panel">
  <h2>Lapsed — expired but not cancelled</h2>
  <table>
    <tr><th>Clinic</th><th>Plan</th><th>Expired</th><th></th></tr>
    @foreach($lapsed as $s)
    <tr>
      <td><a href="{{ route('hq.clinics.show', $s->clinic) }}">{{ $s->clinic->name }}</a></td>
      <td>{{ $s->plan->name }}</td>
      <td><span class="pill bad">{{ $s->expires_at->format('d M Y') }}</span></td>
      <td>
        <form class="inline" method="post" action="{{ route('hq.subscriptions.renew', $s) }}">@csrf<button class="btn ghost">Renew</button></form>
        <form class="inline" method="post" action="{{ route('hq.subscriptions.cancel', $s) }}">@csrf @method('PATCH')<button class="btn ghost">Cancel</button></form>
      </td>
    </tr>
    @endforeach
  </table>
</div>
@endif

<div class="panel">
  <h2>Expiring in the next 30 days</h2>
  @if($expiringSoon->isEmpty())
    <p class="muted">Nothing expiring. Good.</p>
  @else
  <table>
    <tr><th>Clinic</th><th>Plan</th><th>Expires</th><th>Days left</th></tr>
    @foreach($expiringSoon as $s)
    <tr>
      <td><a href="{{ route('hq.clinics.show', $s->clinic) }}">{{ $s->clinic->name }}</a></td>
      <td>{{ $s->plan->name }}</td>
      <td>{{ $s->expires_at->format('d M Y') }}</td>
      <td><span class="pill {{ $s->days_left <= 7 ? 'bad' : 'warn' }}">{{ $s->days_left }}d</span></td>
    </tr>
    @endforeach
  </table>
  @endif
</div>

<div class="panel">
  <h2>Open tickets</h2>
  @if($recentTickets->isEmpty())
    <p class="muted">No open tickets.</p>
  @else
  <table>
    <tr><th>Subject</th><th>Clinic</th><th>Priority</th><th>Status</th><th>Logged</th></tr>
    @foreach($recentTickets as $t)
    <tr>
      <td><a href="{{ route('hq.tickets.show', $t) }}">{{ $t->subject }}</a></td>
      <td>{{ $t->clinic?->name ?? '—' }}</td>
      <td><span class="pill {{ in_array($t->priority, ['urgent','high']) ? 'bad' : 'dim' }}">{{ $t->priority }}</span></td>
      <td><span class="pill">{{ str_replace('_',' ',$t->status) }}</span></td>
      <td class="muted">{{ $t->created_at->diffForHumans() }}</td>
    </tr>
    @endforeach
  </table>
  @endif
</div>

@endsection
