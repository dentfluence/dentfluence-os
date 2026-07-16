@extends('hq.layout')
@section('title', 'Clinics')
@section('content')

<details class="adder">
  <summary>+ Add clinic</summary>
  <div class="panel">
    <form method="post" action="{{ route('hq.clinics.store') }}">
      @csrf
      <div class="grid3">
        <div><label>Name</label><input name="name" required></div>
        <div><label>City</label><input name="city"></div>
        <div><label>Status</label>
          <select name="status">
            <option value="prospect">Prospect</option><option value="trial">Trial</option>
            <option value="active">Active</option><option value="churned">Churned</option>
          </select>
        </div>
        <div><label>Contact name</label><input name="contact_name"></div>
        <div><label>Phone</label><input name="contact_phone"></div>
        <div><label>Email</label><input name="contact_email" type="email"></div>
      </div>
      <p><button class="btn">Save clinic</button></p>
    </form>
  </div>
</details>

<div class="filters" style="margin-bottom:.8rem">
  <a href="{{ route('hq.clinics.index') }}" class="{{ !request('status') ? 'on' : '' }}">All</a>
  @foreach(['active','trial','prospect','churned'] as $s)
    <a href="{{ route('hq.clinics.index', ['status' => $s]) }}" class="{{ request('status') === $s ? 'on' : '' }}">{{ ucfirst($s) }}</a>
  @endforeach
</div>

<div class="panel">
  <table>
    <tr><th>Clinic</th><th>City</th><th>Status</th><th>Live plans</th><th>Open tickets</th></tr>
    @forelse($clinics as $c)
    <tr>
      <td><a href="{{ route('hq.clinics.show', $c) }}">{{ $c->name }}</a></td>
      <td>{{ $c->city ?? '—' }}</td>
      <td><span class="pill {{ $c->status === 'active' ? 'ok' : ($c->status === 'churned' ? 'bad' : 'dim') }}">{{ $c->status }}</span></td>
      <td>
        @forelse($c->activeSubscriptions as $s)
          <span class="pill">{{ $s->plan->name }} · {{ $s->days_left }}d</span>
        @empty <span class="muted">none</span> @endforelse
      </td>
      <td>{{ $c->open_tickets_count ?: '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="5" class="muted">No clinics yet. Add Tulip Dental first.</td></tr>
    @endforelse
  </table>
</div>

@endsection
