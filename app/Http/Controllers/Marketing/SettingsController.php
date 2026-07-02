<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Marketing module settings.
     *
     * Note: this controller lives under App\Http\Controllers\Marketing —
     * it is distinct from App\Http\Controllers\Settings\SettingsController.
     */
    public function index(): View
    {
        return view('marketing.settings.index');
    }
}
