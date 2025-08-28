<?php
use App\Http\Controllers\BonusController;
use Illuminate\Support\Facades\Route;

Route::get('bonuses', [BonusController::class, 'index'])->name('bonuses.index');
Route::get('bonuses/{period}', [BonusController::class, 'show'])->name('bonuses.show');