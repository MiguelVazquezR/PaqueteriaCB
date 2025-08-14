<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;

// Usamos `except` porque la creación de nóminas podría ser un proceso
// automático más que un formulario estándar de "create".
Route::resource('payrolls', PayrollController::class)->except(['create', 'edit', 'update']);