<?php

use App\Http\Controllers\Setting\HolidayController;
use App\Http\Controllers\Setting\PermissionController;
use App\Http\Controllers\Setting\RolePermissionController;
use App\Http\Controllers\Setting\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->name('settings.')->group(function () {
    Route::resource('roles-permissions', RolePermissionController::class)
        ->parameters(['roles-permissions' => 'role'])
        ->except(['show', 'edit', 'update']);
    Route::put('roles-permissions/{role}/permissions', [RolePermissionController::class, 'updatePermissions'])->name('roles-permissions.updatePermissions')
        ->middleware('can:ver_roles_permisos'); // El permiso se aplica a todas las rutas de este resource.
    Route::resource('holidays', HolidayController::class)->except(['show']);
    Route::resource('schedules', ScheduleController::class)->except(['show']);
    Route::resource('permissions', PermissionController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('can:ver_roles_permisos');
});
