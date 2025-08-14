<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleDetailController;

Route::resource('schedules', ScheduleController::class);

// Las rutas para los detalles del horario, anidadas dentro de un horario
Route::resource('schedules.details', ScheduleDetailController::class)->shallow();