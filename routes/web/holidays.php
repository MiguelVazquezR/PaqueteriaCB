<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HolidayRuleController;
use App\Http\Controllers\ConcreteHolidayController;

// Rutas para las reglas de días festivos (ej. "Navidad", "Año Nuevo")
Route::resource('holiday-rules', HolidayRuleController::class);

// Rutas para las fechas concretas, anidadas dentro de una regla
// ej. /holiday-rules/1/concrete-holidays/create
Route::resource('holiday-rules.concrete-holidays', ConcreteHolidayController::class)
    ->shallow()
    ->except(['index']); // No necesitamos un "index" anidado