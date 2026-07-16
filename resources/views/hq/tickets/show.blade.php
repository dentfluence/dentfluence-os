@extends('hq.layout')
@section('title', 'Ticket #'.$ticket->id)
@section('content')

<div class="panel">
  <h2>{{ $ticket->subject }}</h2>
  <p class="muted">
    {{ $ticket->clinic?->name ?? 'No clinic' }} · via {{ str_replace('_',' ',$ticket->channel) }}
    · logged {{ $ticket->created_at->format('d M Y H:i') }}
    @if($ticket->resolved_at) · resolved {{ $ticket->resolved_at->format('d M Y H:i') }} @endif
  </p>
  @if($ticket->body)<p>{{ $ticket->body }}</p>@endif
</div>

<div class="panel">
  <h2>Update</h2>
  <form method="post" action="{{ route('hq.tickets.update', $ticket) }}">
    @csrf @method('PATCH')
    <div class="grid2">
      <div><label>Status</label>
        <select name="status">
          @foreach(['open','in_progress','waiting_on_clinic','resolved','closed'] as $s)
            <option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
          @endforeach
        </select>
      </div>
      <div><label>Priority</label>
        <select name="priority">
          @foreach(['low','normal','high','urgent'] as $p)
            <option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div style="margin-top:.8rem"><label>Resolution notes</label><textarea name="resolution" rows="3">{{ $ticket->resolution }}</textarea></div>
    <p><button class="btn">Save</button></p>
  </form>
</div>

@endsection
