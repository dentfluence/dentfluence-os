<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function index():  View { return view('communication.opportunities.index',  ['pageTitle'=>'Opportunity Engine','activeNav'=>'opportunities']); }
    public function board():  View { return view('communication.opportunities.board',  ['pageTitle'=>'Opportunity Board', 'activeNav'=>'opportunities']); }
    public function detail(): View { return view('communication.opportunities.detail', ['pageTitle'=>'Opportunity',       'activeNav'=>'opportunities']); }
    public function store()        { abort(501, 'Session 7'); }
}
