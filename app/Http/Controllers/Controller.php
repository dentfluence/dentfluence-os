<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Enables $this->authorize() in every controller. Added 2026-08-26 with
    // the first policy in the app (AppointmentPolicy); purely additive - no
    // existing controller defines an authorize() method.
    use AuthorizesRequests;
}
