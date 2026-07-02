<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpController extends Controller
{
    /**
     * Display the Help & Support guide page.
     */
    public function index()
    {
        return view('help.index');
    }
}
