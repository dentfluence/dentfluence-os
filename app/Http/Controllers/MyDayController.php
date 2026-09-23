<?php

namespace App\Http\Controllers;

use App\Services\MyDay\MyDayQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * My Day — one ordered queue of work for the signed-in person.
 *
 * One action, no writes, no state. Everything it shows belongs to a module
 * and is changed there; this page only decides what order to face it in.
 */
class MyDayController extends Controller
{
    public function index(Request $request, MyDayQueue $queue)
    {
        $data = $queue->build(Auth::user());

        return view('my-day.index', [
            'bands' => $data['bands'],
            'total' => $data['total'],
        ]);
    }
}
