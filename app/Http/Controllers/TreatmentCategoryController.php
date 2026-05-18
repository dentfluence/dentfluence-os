<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('treatment_categories')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    public function treatments(int $category)
    {
        $treatments = DB::table('treatment_types')
            ->where('treatment_category_id', $category)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($treatments);
    }
}