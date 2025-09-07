<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HolidayRuleController;

// Rutas para las reglas de días festivos (ej. "Navidad", "Año Nuevo")
Route::resource('holiday-rules', HolidayRuleController::class);