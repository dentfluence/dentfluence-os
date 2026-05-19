<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PrmController extends Controller
{
    public function index():  View { return view('communication.prm.index',       ['pageTitle'=>'PRM Pipeline',  'activeNav'=>'prm']); }
    public function board():  View { return view('communication.prm.board',        ['pageTitle'=>'Pipeline Board','activeNav'=>'prm']); }
    public function detail(): View { return view('communication.prm.lead-detail',  ['pageTitle'=>'Lead Detail',  'activeNav'=>'prm']); }
    public function store()        { abort(501, 'Session 3'); }
    public function moveStage()    { abort(501, 'Session 3'); }
}
