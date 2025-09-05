<?php
use App\Http\Controllers\BonusController;
use Illuminate\Support\Facades\Route;

Route::get('bonuses', [BonusController::class, 'index'])->name('bonuses.index');
Route::get('bonuses/{period}', [BonusController::class, 'show'])->name('bonuses.show');
Route::post('bonuses/{period}/finalize', [BonusController::class, 'finalize'])->name('bonuses.finalize');
Route::get('bonuses/{period}/print', [BonusController::class, 'print'])->name('bonuses.print');
Route::post('bonuses/{period}/recalculate', [BonusController::class, 'recalculate'])->name('bonuses.recalculate');