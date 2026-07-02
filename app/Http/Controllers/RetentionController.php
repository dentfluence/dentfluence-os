<?php

namespace App\Http\Controllers;

use App\Services\RetentionService;

/**
 * RetentionController (DPDP 5.4)
 * ------------------------------
 * Read-only dashboard showing retention policies and how many records are
 * currently past their window (dry run). No purging happens from here.
 */
class RetentionController extends Controller
{
    public function __construct(private RetentionService $retention) {}

    public function index()
    {
        $report = $this->retention->report();
        return view('retention.index', compact('report'));
    }
}
