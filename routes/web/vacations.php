<?php

use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::post('vacations/{employee}/initial-balance', [VacationController::class, 'updateInitialBalance'])->name('vacations.updateInitialBalance')->middleware('can:vacaciones_usuarios');
Route::post('vacations/{employee}/transaction', [VacationController::class, 'storeTransaction'])->name('vacations.storeTransaction')->middleware('can:vacaciones_usuarios');