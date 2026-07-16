@extends('hq.layout')
@section('title', 'Subscriptions')
@section('content')

<details class="adder">
  <summary>+ Add subscription</summary>
  <div class="panel">
    <form method="post" action="{{ route('hq.subscriptions.store') }}">
      @csrf
      <div class="grid3">
        <div><label>Clinic</label>
          <select name="clinic_id" required>
            @foreach($clinics as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
          </select>
        </div>
        <div><label>Plan</label>
          <select name="plan_id" required>
            @foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} (₹{{ number_format($p->monthly_price) }}/mo · ₹{{ number_format($p->annual_price) }}/yr)</option>@endforeach
          </select>
        </div>
        <div><label>Cycle</label>
          <select name="billing_cycle"><option value="monthly">Monthly</option><option value="annual">Annual</option></select>
        </div>
        <div><label>Amount charged (₹)</label><input name="amount" type="number" min="0" required></div>
        <div><label>Starts</label><input name="starts_at" type="date" value="{{ now()->format('Y-m-d') }}" required></div>
        <div><label>Expires</label><input name="expires_at" type="date" required></div>
      </div>
      <div style="margin-top:.8rem"><label>Notes</label><input name="notes" placeholder="e.g. launch discount, quoted price locked"></div>
      <p><button class="btn">Save subscription</button></p>
    </form>
  </div>
</details>

<div class="filters" style="margin-bottom:.8rem">
  <a href="{{ route('hq.subscriptions.index') }}" class="{{ !$filter ? 'on' : '' }}">All</a>
  @foreach(['live','expiring','lapsed','cancelled'] as $f)
    <a href="{{ route('hq.subscriptions.index', ['filter' => $f]) }}" class="{{ $filter === $f ? 'on' : '' }}">{{ ucfirst($f) }}</a>
  @endforeach
</div>

<div class="panel">
  <table>
    <tr><th>Clinic</th><th>Plan</th><th>Cycle</th><th>Amount</th><th>Expires</th><th>State</th><th></th></tr>
    @forelse($subscriptions as $s)
    <tr>
      <td><a href="{{ route('hq.clinics.show', $s->clinic) }}">{{ $s->clinic->name }}</a></td>
      <td>{{ $s->plan->name }}</td>
      <td>{{ $s->billing_cycle }}</td>
      <td>₹{{ number_format($s->amount) }}</td>
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
    @empty
    <tr><td colspan="7" class="muted">No subscriptions match.</td></tr>
    @endforelse
  </table>
</div>

@endsection
