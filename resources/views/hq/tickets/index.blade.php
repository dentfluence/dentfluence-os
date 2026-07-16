@extends('hq.layout')
@section('title', 'Support')
@section('content')

<details class="adder">
  <summary>+ Log ticket</summary>
  <div class="panel">
    <form method="post" action="{{ route('hq.tickets.store') }}">
      @csrf
      <div class="grid3">
        <div><label>Clinic</label>
          <select name="clinic_id">
            <option value="">— none / general —</option>
            @foreach($clinics as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
          </select>
        </div>
        <div><label>Channel</label>
          <select name="channel">
            <option value="whatsapp">WhatsApp</option><option value="phone">Phone</option>
            <option value="email">Email</option><option value="in_person">In person</option>
          </select>
        </div>
        <div><label>Priority</label>
          <select name="priority">
            <option value="normal">Normal</option><option value="low">Low</option>
            <option value="high">High</option><option value="urgent">Urgent</option>
          </select>
        </div>
      </div>
      <div style="margin-top:.8rem"><label>Subject</label><input name="subject" required></div>
      <div style="margin-top:.8rem"><label>Details</label><textarea name="body" rows="3"></textarea></div>
      <p><button class="btn">Log ticket</button></p>
    </form>
  </div>
</details>

<div class="filters" style="margin-bottom:.8rem">
  <a href="{{ route('hq.tickets.index') }}" class="{{ !$status ? 'on' : '' }}">All</a>
  @foreach(['open','resolved','closed'] as $f)
    <a href="{{ route('hq.tickets.index', ['status' => $f]) }}" class="{{ $status === $f ? 'on' : '' }}">{{ ucfirst($f) }}</a>
  @endforeach
</div>

<div class="panel">
  <table>
    <tr><th>Subject</th><th>Clinic</th><th>Channel</th><th>Priority</th><th>Status</th><th>Logged</th></tr>
    @forelse($tickets as $t)
    <tr>
      <td><a href="{{ route('hq.tickets.show', $t) }}">{{ $t->subject }}</a></td>
      <td>{{ $t->clinic?->name ?? '—' }}</td>
      <td>{{ str_replace('_',' ',$t->channel) }}</td>
      <td><span class="pill {{ in_array($t->priority, ['urgent','high']) ? 'bad' : 'dim' }}">{{ $t->priority }}</span></td>
      <td><span class="pill {{ in_array($t->status, ['resolved','closed']) ? 'ok' : '' }}">{{ str_replace('_',' ',$t->status) }}</span></td>
      <td class="muted">{{ $t->created_at->format('d M Y') }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="muted">No tickets match.</td></tr>
    @endforelse
  </table>
</div>

@endsection
