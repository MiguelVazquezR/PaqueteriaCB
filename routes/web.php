<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', 'login');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    require __DIR__ . '/web/attendances.php';
    require __DIR__ . '/web/bonuses.php';
    require __DIR__ . '/web/branches.php';
    require __DIR__ . '/web/employees.php';
    require __DIR__ . '/web/holidays.php';
    require __DIR__ . '/web/incidents.php';
    require __DIR__ . '/web/payrolls.php';
    require __DIR__ . '/web/schedules.php';
    require __DIR__ . '/web/settings.php';
    require __DIR__ . '/web/users.php';
    require __DIR__ . '/web/vacations.php';
});
