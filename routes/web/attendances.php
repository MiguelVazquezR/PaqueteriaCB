<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

// Ruta para Entrada / Salida
Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
// Ruta para Inicio / Fin de Descanso
Route::post('attendances/break', [AttendanceController::class, 'storeBreak'])->name('attendances.storeBreak');

// --- RUTAS PARA EL MODO QUIOSCO PÚBLICO ---
// Estas rutas NO requieren autenticación.
Route::get('attendance/kiosk', [AttendanceController::class, 'kiosk'])->name('attendances.kiosk');
Route::post('attendance/kiosk', [AttendanceController::class, 'kioskStore'])->name('attendances.kiosk.store');
Route::post('attendance/kiosk/break', [AttendanceController::class, 'kioskStoreBreak'])->name('attendances.kiosk.storeBreak');