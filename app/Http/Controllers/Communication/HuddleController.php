<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HuddleController extends Controller
{
    public function widgets():        View { return view('communication.huddle.widgets',             ['pageTitle'=>'Huddle Widgets', 'activeNav'=>'huddle']); }
    public function overdueSummary(): View { return view('communication.huddle.overdue-summary',     ['pageTitle'=>'Overdue Summary','activeNav'=>'huddle']); }
    public function alerts():         View { return view('communication.huddle.communication-alerts',['pageTitle'=>'Comm Alerts',    'activeNav'=>'huddle']); }
}
