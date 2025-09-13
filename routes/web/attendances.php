<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

// Ruta para Entrada / Salida
Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
// Ruta para Inicio / Fin de Descanso
Route::post('attendances/break', [AttendanceController::class, 'storeBreak'])->name('attendances.storeBreak');