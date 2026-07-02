<?php
namespace App\Http\Controllers\Communication;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View { return view('communication.templates.index', ['pageTitle'=>'Templates',     'activeNav'=>'templates']); }
    public function edit():  View { return view('communication.templates.editor',['pageTitle'=>'Edit Template','activeNav'=>'templates']); }
}
