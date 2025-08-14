<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingController;

// Ruta para mostrar la página de configuración
Route::get('settings', [SettingController::class, 'index'])->name('settings.index');

// Ruta para guardar los cambios en la configuración
Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');