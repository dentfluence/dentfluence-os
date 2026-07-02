<?php

use App\Http\Controllers\ReviewPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reviews — PUBLIC routes (Phase B item 2.4)
|--------------------------------------------------------------------------
| No auth: these are the patient-facing rating pages reached from the unique
| link we WhatsApp them. Admin/dashboard routes live in routes/communication.php
| (named communication.reviews.*) behind auth.
*/

Route::get('/r/{token}',  [ReviewPublicController::class, 'show'])->name('review.show');
Route::post('/r/{token}', [ReviewPublicController::class, 'submit'])->name('review.submit');
