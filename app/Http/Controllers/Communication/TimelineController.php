<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function index():   View { return view('communication.timeline.index',            ['pageTitle'=>'Timeline',        'activeNav'=>'timeline']); }
    public function patient(): View { return view('communication.timeline.patient-timeline', ['pageTitle'=>'Patient Timeline','activeNav'=>'timeline']); }
}
