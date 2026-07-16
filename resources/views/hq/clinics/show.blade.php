@extends('hq.layout')
@section('title', $clinic->name)
@section('content')

<div class="cards">
  <div class="card"><div class="num">{{ ucfirst($clinic->status) }}</div><div class="lbl">Status</div></div>
  <div class="card"><div class="num">{{ $clinic->activeSubscriptions()->count() }}</div><div class="lbl">Live subscriptions</div></div>
  <div class="card"><div class="num">{{ $clinic->tickets->whereNotIn('status', ['resolved','closed'])->count() }}</div><div class="lbl">Open tickets</div></div>
  <div class="card"><div class="num">{{ $clinic->onboarded_at?->format('d M Y') ?? '—' }}</div><div class="lbl">Onboarded</div></div>
</div>

<div class="panel">
  <h2>Pass — doors this clinic can open</h2>
  @php($passes = $clinic->passes())
  @if($passes->isEmpty())
    <p class="muted">No live pass. Every store door is locked for this clinic.</p>
  @elseif($passes->contains('*'))
    <p><span class="pill ok">PRO PASS — all stores &amp; modules</span></p>
  @else
    <p>
      @foreach($passes as $p)
        <span class="pill ok">{{ strtoupper($p) }}</span>
      @endforeach
    </p>
  @endif
</div>

<div class="panel">
  <h2>Details & notes</h2>
  <form method="post" action="{{ route('hq.clinics.update', $clinic) }}">
    @csrf @method('PATCH')
    <div class="grid3">
      <div><label>Status</label>
        <select name="status">
          @foreach(['prospect','trial','active','churned'] as $s)
            <option value="{{ $s }}" @selected($clinic->status === $s)>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
      </div>
      <div><label>Contact</label><input name="contact_name" value="{{ $clinic->contact_name }}"></div>
      <div><label>Phone</label><input name="contact_phone" value="{{ $clinic->contact_phone }}"></div>
      <div><label>Email</label><input name="contact_email" type="email" value="{{ $clinic->contact_email }}"></div>
      <div><label>Onboarded</label><input name="onboarded_at" type="date" value="{{ $clinic->onboarded_at?->format('Y-m-d') }}"></div>
    </div>
    <div style="margin-top:.8rem"><label>Notes</label><textarea name="notes" rows="3">{{ $clinic->notes }}</textarea></div>
    <p><button class="btn">Save</button></p>
  </form>
</div>

<div class="panel">
  <h2>Subscriptions</h2>
  @if($clinic->subscriptions->isEmpty())
    <p class="muted">No subscriptions. Add one from the <a href="{{ route('hq.subscriptions.index') }}">Subscriptions</a> page.</p>
  @else
  <table>
    <tr><th>Plan</th><th>Cycle</th><th>Amount</th><th>Starts</th><th>Expires</th><th>State</th><th></th></tr>
    @foreach($clinic->subscriptions->sortByDesc('expires_at') as $s)
    <tr>
      <td>{{ $s->plan->name }}</td>
      <td>{{ $s->billing_cycle }}</td>
      <td>₹{{ number_format($s->amount) }}</td>
      <td>{{ $s->starts_at->format('d M Y') }}</td>
      <td>{{ $s->expires_at->format('d M Y') }}</td>
      <td>
        @if($s->status === 'cancelled') <span class="pill dim">cancelled</span>
        @elseif($s->days_left < 0) <span class="pill bad">lapsed</span>
        @elseif($s->days_left <= 30) <span class="pill warn">{{ $s->days_left }}d left</span>
        @else <span class="pill ok">live · {{ $s->days_left }}d</span>
        @endif
      </td>
      <td>
        @if($s->status === 'active')
        <form class="inline" method="post" action="{{ route('hq.subscriptions.renew', $s) }}">@csrf<button class="btn ghost">Renew</button></form>
        <form class="inline" method="post" action="{{ route('hq.subscriptions.cancel', $s) }}">@csrf @method('PATCH')<button class="btn ghost">Cancel</button></form>
        @endif
      </td>
    </tr>
    @endforeach
  </table>
  @endif
</div>

<div class="panel">
  <h2>Tickets</h2>
  @if($clinic->tickets->isEmpty())
    <p class="muted">No tickets from this clinic.</p>
  @else
  <table>
    <tr><th>Subject</th><th>Priority</th><th>Status</th><th>Logged</th></tr>
    @foreach($clinic->tickets as $t)
    <tr>
      <td><a href="{{ route('hq.tickets.show', $t) }}">{{ $t->subject }}</a></td>
      <td>{{ $t->priority }}</td>
      <td><span class="pill">{{ str_replace('_',' ',$t->status) }}</span></td>
      <td class="muted">{{ $t->created_at->format('d M Y') }}</td>
    </tr>
    @endforeach
  </table>
  @endif
</div>

@endsection
