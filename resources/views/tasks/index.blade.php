{{--
    Tasks — the staff work list. Rebuilt as rows (Task Manager V2).

    WHY ROWS AND NOT CARDS: the previous screen rendered one large card per
    task, so four or five tasks filled the viewport and reception scrolled to
    find anything. A receptionist works this list under time pressure between
    patients; density is the feature.

    WHY NO BOARD: a kanban board optimises for moving work between columns.
    Nobody here does that. They read what is due, act, and log what happened.

    Every control on this page posts to the server. The V1 screen carried a
    search box, a staff dropdown, counter cards and a Daily/Weekly/Monthly
    switch whose Alpine state nothing ever read — all four were decoration.

    THE MARKUP MOVED (23 Sep). It lives in tasks/_board.blade.php because My
    Day renders the same board inline, and the two must be the same board, not
    two screens that resemble each other. This page is now the full-page frame
    around it: sidebar, page title, and the board at full size.
--}}
@extends('layouts.app')
@section('page-title', 'Tasks')

@section('content')
    @include('tasks._board')
@endsection
