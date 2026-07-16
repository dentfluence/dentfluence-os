<?php

namespace App\Http\Controllers;

use App\Support\HelpContent;

class HelpController extends Controller
{
    /**
     * Help Centre — core workflows + screen-by-screen guides, all driven
     * by the single content registry (resources/help/content.php).
     */
    public function index()
    {
        $user    = auth()->user();
        $isAdmin = $user ? ($user->isAdminRole() || $user->isAdmin()) : false;

        return view('help.index', [
            'workflows' => HelpContent::workflows(),
            'screens'   => HelpContent::screens($isAdmin),
            'isAdmin'   => $isAdmin,
        ]);
    }
}
