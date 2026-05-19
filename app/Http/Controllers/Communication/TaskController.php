<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index():     View { return view('communication.tasks.index',     ['pageTitle'=>'Tasks',          'activeNav'=>'tasks']); }
    public function queue():     View { return view('communication.tasks.queue',     ['pageTitle'=>'Task Queue',     'activeNav'=>'tasks']); }
    public function myTasks():   View { return view('communication.tasks.my-tasks',  ['pageTitle'=>'My Tasks',       'activeNav'=>'tasks']); }
    public function escalated(): View { return view('communication.tasks.escalated', ['pageTitle'=>'Escalated Tasks','activeNav'=>'tasks']); }
}
