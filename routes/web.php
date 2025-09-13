<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'login');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    require __DIR__ . '/web/attendances.php';
    require __DIR__ . '/web/bonuses.php';
    require __DIR__ . '/web/branches.php';
    require __DIR__ . '/web/incidents.php';
    require __DIR__ . '/web/settings.php';
    require __DIR__ . '/web/users.php';
    require __DIR__ . '/web/vacations.php';
});
// --- RUTAS PARA EL MODO QUIOSCO PÚBLICO ---
// Estas rutas NO requieren autenticación.
Route::get('attendance/kiosk', [AttendanceController::class, 'kiosk'])->name('attendances.kiosk');
Route::post('attendance/kiosk', [AttendanceController::class, 'kioskStore'])->name('attendances.kiosk.store');
Route::post('attendance/kiosk/break', [AttendanceController::class, 'kioskStoreBreak'])->name('attendances.kiosk.storeBreak');

//artisan commands -------------------
Route::get('/clear-all', function () {
    Artisan::call('optimize:clear');
    return 'cleared.';
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'cleared.';
});

Route::get('/payroll-cycle', function () {
    Artisan::call('payroll:cycle');
    return 'Listo!.';
});

Route::get('/bonus-generate', function (Request $request) {
    // 1. Obtenemos el parámetro 'month' de la URL (ej: ?month=2025-09)
    $month = $request->query('month');
    // 2. (Opcional pero recomendado) Validamos que el mes se haya proporcionado
    if (!$month) {
        return response('Error: Debes proporcionar el mes. Ejemplo: /bonus-generate?month=AAAA-MM', 400);
    }
    // 3. Ejecutamos el comando pasando el parámetro como un array
    Artisan::call('bonuses:generate', [
        '--month' => $month
    ]);
    // 4. Devolvemos una respuesta más clara
    return "Reporte de bonos generado para el mes: " . e($month);
});