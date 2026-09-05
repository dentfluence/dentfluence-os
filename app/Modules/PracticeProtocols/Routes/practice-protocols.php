<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\PracticeProtocols\Controllers\PracticeProtocolController;
use App\Modules\PracticeProtocols\Controllers\PracticeProtocolMaterialController;

/*
|--------------------------------------------------------------------------
| Practice Protocols — admin catalog management
|--------------------------------------------------------------------------
| Define standard recurring duties (per role) and attach their SOP/materials.
| Guarded by the `practice_protocols` module permission (admin / manager).
*/
Route::middleware(['auth', 'web', 'module:practice_protocols'])
    ->prefix('practice-protocols')
    ->name('practice-protocols.')
    ->group(function () {

        Route::get('/',                      [PracticeProtocolController::class, 'index'])->name('index');
        Route::get('/create',                [PracticeProtocolController::class, 'create'])->name('create');
        Route::post('/',                     [PracticeProtocolController::class, 'store'])->name('store')->middleware('module:practice_protocols,edit');
        Route::get('/{protocol}/edit',       [PracticeProtocolController::class, 'edit'])->name('edit');
        Route::put('/{protocol}',            [PracticeProtocolController::class, 'update'])->name('update')->middleware('module:practice_protocols,edit');
        Route::delete('/{protocol}',         [PracticeProtocolController::class, 'destroy'])->name('destroy')->middleware('module:practice_protocols,delete');

        // Materials attached to a protocol (SOP steps / file / link)
        Route::post('/{protocol}/materials',     [PracticeProtocolMaterialController::class, 'store'])->name('materials.store')->middleware('module:practice_protocols,edit');
        Route::delete('/materials/{material}',   [PracticeProtocolMaterialController::class, 'destroy'])->name('materials.destroy')->middleware('module:practice_protocols,delete');
    });
