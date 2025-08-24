<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IncidentTypeController;

Route::resource('incident-types', IncidentTypeController::class);

Route::post('/incidents/update-attendance', [IncidentController::class, 'updateAttendance'])->name('incidents.updateAttendance');
Route::post('/incidents/toggle-late-status', [IncidentController::class, 'toggleLateStatus'])->name('incidents.toggleLateStatus');
Route::post('/incidents/store-day-incident', [IncidentController::class, 'storeDayIncident'])->name('incidents.storeDayIncident');
Route::post('/incidents/remove-day-incident', [IncidentController::class, 'removeDayIncident'])->name('incidents.removeDayIncident');
Route::post('/incidents/update-comment', [IncidentController::class, 'updateComment'])->name('incidents.updateComment');
Route::get('/incidents/{period}/pre-payroll', [IncidentController::class, 'prePayroll'])->name('incidents.prePayroll');

// Rutas de resource
Route::get('/incidents/{period}', [IncidentController::class, 'show'])->name('incidents.show');
Route::resource('incidents', IncidentController::class)->except(['show']);
