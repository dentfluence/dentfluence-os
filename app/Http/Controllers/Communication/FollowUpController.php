<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index():    View { return view('communication.followup.index',       ['pageTitle'=>'Follow-up Engine',  'activeNav'=>'followup']); }
    public function queue():    View { return view('communication.followup.queue',        ['pageTitle'=>'Follow-up Queue',   'activeNav'=>'followup']); }
    public function overdue():  View { return view('communication.followup.overdue',      ['pageTitle'=>'Overdue Follow-ups','activeNav'=>'followup']); }
    public function calendar(): View { return view('communication.followup.calendar',     ['pageTitle'=>'Follow-up Calendar','activeNav'=>'followup']); }
    public function recalls():  View { return view('communication.followup.recall-queue', ['pageTitle'=>'Recall Queue',      'activeNav'=>'followup']); }
}
