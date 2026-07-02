<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    /**
     * Main Marketing module — defaults to Publish tab.
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'publish');

        return view('marketing.index', compact('activeTab'));
    }

    public function publish()
    {
        return view('marketing.index', ['activeTab' => 'publish']);
    }

    public function calendar()
    {
        return view('marketing.index', ['activeTab' => 'calendar']);
    }

    public function ideas()
    {
        return view('marketing.index', ['activeTab' => 'ideas']);
    }

    public function analytics()
    {
        return view('marketing.index', ['activeTab' => 'analytics']);
    }

    public function accountability()
    {
        return view('marketing.index', ['activeTab' => 'accountability']);
    }
}
