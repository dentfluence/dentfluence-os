<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Billing index — list all invoices.
     * Full implementation in Session 5.
     */
    public function index()
    {
        return view('billing.index');
    }

    /**
     * Create new invoice form.
     * Full implementation in Session 5.
     */
    public function create()
    {
        return view('billing.index'); // placeholder until Session 5
    }
}
