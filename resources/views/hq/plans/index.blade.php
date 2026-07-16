@extends('hq.layout')
@section('title', 'Plans & bundles')
@section('content')

<p class="muted" style="margin-top:0">List prices from the 2026 pricing doc — illustrative until validated. Actual charged amounts live on each subscription, so a quoted price never changes when list price does.</p>

<div class="panel">
  <table>
    <tr><th>Plan</th><th>Kind</th><th>Monthly</th><th>Annual</th><th>Live subs</th><th>Status</th><th></th></tr>
    @foreach($plans as $p)
    <tr>
      <td><strong>{{ $p->name }}</strong><br><span class="muted">{{ $p->description }}</span></td>
      <td><span class="pill dim">{{ str_replace('_',' ',$p->kind) }}</span></td>
      <td>₹{{ number_format($p->monthly_price) }}</td>
      <td>₹{{ number_format($p->annual_price) }}</td>
      <td>{{ $p->live_count ?: '—' }}</td>
      <td><span class="pill {{ $p->is_active ? 'ok' : 'dim' }}">{{ $p->is_active ? 'active' : 'inactive' }}</span></td>
      <td>
        <form class="inline" method="post" action="{{ route('hq.plans.toggle', $p) }}">
          @csrf @method('PATCH')
          <button class="btn ghost">{{ $p->is_active ? 'Deactivate' : 'Activate' }}</button>
        </form>
      </td>
    </tr>
    @endforeach
  </table>
</div>

@endsection
