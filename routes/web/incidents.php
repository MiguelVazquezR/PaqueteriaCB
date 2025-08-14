<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IncidentTypeController;

// Rutas para los tipos de incidencia (ej. Falta, Retardo, etc.)
Route::resource('incident-types', IncidentTypeController::class);

// Rutas para las incidencias de los empleados
Route::resource('incidents', IncidentController::class);