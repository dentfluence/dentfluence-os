<?php

namespace App\Modules\Hq\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hq\Models\Clinic;
use App\Modules\Hq\Models\Subscription;
use App\Modules\Hq\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $liveSubs = Subscription::live()->with(['clinic', 'plan'])->get();

        return view('hq.dashboard', [
            'activeClinics'  => Clinic::where('status', 'active')->count(),
            'liveSubCount'   => $liveSubs->count(),
            'mrr'            => $liveSubs->sum->monthly_value,
            'openTickets'    => Ticket::open()->count(),
            'expiringSoon'   => Subscription::expiringWithin(30)->with(['clinic', 'plan'])->orderBy('expires_at')->get(),
            'lapsed'         => Subscription::lapsed()->with(['clinic', 'plan'])->orderBy('expires_at')->get(),
            'recentTickets'  => Ticket::open()->with('clinic')->latest()->limit(8)->get(),
        ]);
    }
}
