<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

// Usamos resource para tener todas las rutas CRUD básicas.
// Podrías limitarlas con ->only(['index', 'show', 'store']) si no
// se permite la edición o borrado directo.
Route::resource('attendances', AttendanceController::class);