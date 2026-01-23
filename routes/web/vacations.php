<?php

use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:vacaciones_usuarios')->group(function () {
    Route::post('vacations/{employee}/initial-balance', [VacationController::class, 'updateInitialBalance'])
        ->name('vacations.updateInitialBalance');
        
    Route::post('vacations/{employee}/transaction', [VacationController::class, 'storeTransaction'])
        ->name('vacations.storeTransaction');
        
    Route::delete('vacations/transaction/{vacationLedger}', [VacationController::class, 'destroyTransaction'])
        ->name('vacations.destroyTransaction');

    // --- NUEVA RUTA PARA PRIMA VACACIONAL ---
    // Recibe el ID del periodo (año) específico que se va a pagar
    Route::post('vacations/premium/{vacationPeriod}/pay', [VacationController::class, 'markPremiumAsPaid'])
        ->name('vacations.markPremiumAsPaid');
});