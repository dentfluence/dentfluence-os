{{--
|==========================================================================
| Today's Actions — Relationship Engine Phase 2
| /relationship/today  and  /relationship/today/pending
|
| THE MARKUP MOVED (23 Sep). It lives in relationship/today/_board.blade.php
| because My Day renders the same board inline, and the two must be the same
| board — same W-10 patient folding, same call drawer — not two screens that
| resemble each other. This file is now the full-page frame around it.
|
| Variables come from TodayController::boardPayload(), which is public for
| exactly this reason. Do not rebuild that payload by hand.
|
| Uses:  relationship.layouts.app  →  layouts.app
| The board pushes its own stylesheet to the 'styles' stack, so nothing here
| needs a head-extra section any more.
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', "Today's Actions")

@section('relationship-content')
    @include('relationship.today._board')
@endsection
