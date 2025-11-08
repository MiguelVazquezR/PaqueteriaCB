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

Route::get('/set-initial-balance', function () {
    Artisan::call('vacations:set-initial-balance');
    return 'Listo!.';
});

// Route::get('/phpinfo', function () {
//     return phpinfo();
// });

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



use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
Route::get('/admin/fix-attendances', function (Request $request) {
    
    // Array para guardar los logs y mostrarlos al final
    $log = [];
    $log[] = "Iniciando la limpieza de TODA la tabla de asistencias...";
    $log[] = "Procesando empleados en lotes para no saturar la memoria...";
    $log[] = "===================================================================";
    
    // Variables para las estadísticas.
    // Las pasamos por referencia (con '&') al closure del chunk.
    $totalFixedDays = 0;
    $totalOrphansFixed = 0;

    // 1. OBTENER DATOS POR CHUNKS (LOTES DE 100)
    Employee::with(['attendances' => function ($query) {
            // Ordenar asistencias es crucial para la lógica de "último fichaje"
            $query->orderBy('created_at', 'asc');
        }])
        ->whereHas('attendances') // Solo empleados que tengan al menos un registro
        ->chunk(100, function ($employees) use (&$log, &$totalFixedDays, &$totalOrphansFixed) {

        // 2. PROCESAR CADA EMPLEADO DEL LOTE
        foreach ($employees as $employee) {
            
            // Agrupamos las asistencias por día
            $attendancesByDay = $employee->attendances->groupBy(function ($att) {
                return Carbon::parse($att->created_at)->format('Y-m-d');
            });

            // Iteramos sobre cada día del empleado
            foreach ($attendancesByDay as $date => $attendancesToday) {
                $entries = $attendancesToday->where('type', 'entry');
                $exits = $attendancesToday->where('type', 'exit');
                $breakStarts = $attendancesToday->where('type', 'break_start');
                $breakEnds = $attendancesToday->where('type', 'break_end');

                $fixedThisDay = false; // Bandera para contar el día solo una vez

                // --- LÓGICA 1: ARREGLAR ENTRADAS DUPLICADAS ---
                if ($entries->count() > 1) {
                    $fixedThisDay = true;
                    $log[] = "\n[!] Empleado: {$employee->id} ({$employee->first_name}) - Día: $date";
                    $log[] = "    -> Se encontraron {$entries->count()} entradas. Corrigiendo...";

                    // Obtenemos todas las entradas erróneas (todas menos la primera)
                    $fakeEntries = $entries->skip(1);
                    $idsToDelete = $fakeEntries->pluck('id');
                    
                    // Si no hay salida, convertimos la *última* entrada errónea en una salida.
                    if ($exits->count() === 0) {
                        $lastFakeEntry = $fakeEntries->last();
                        
                        $lastFakeEntry->update([
                            'type' => 'exit',
                            'late_minutes' => null, // Limpiamos los minutos de retardo erróneos
                            'late_ignored' => false,
                        ]);

                        // Quitamos esta ID de la lista de eliminación
                        $idsToDelete = $idsToDelete->filter(fn($id) => $id !== $lastFakeEntry->id);
                        $log[] = "    -> Se convirtió la entrada de las {$lastFakeEntry->created_at->format('H:i:s')} en una SALIDA.";
                    }

                    // Eliminamos cualquier otra entrada duplicada que haya quedado
                    if ($idsToDelete->isNotEmpty()) {
                        Attendance::whereIn('id', $idsToDelete)->delete();
                        $log[] = "    -> Se eliminaron {$idsToDelete->count()} entradas duplicadas restantes.";
                    }
                }

                // --- LÓGICA 2: ARREGLAR DESCANSOS HUÉRFANOS ---
                // (Crear un 'break_end' 30 mins después)
                if ($breakStarts->count() > $breakEnds->count()) {
                    // Buscamos el último evento de descanso
                    $lastBreakRecord = $attendancesToday->whereIn('type', ['break_start', 'break_end'])->last();
                    
                    // Si el último evento fue un 'break_start', está huérfano
                    if ($lastBreakRecord && $lastBreakRecord->type === 'break_start') {
                        if (!$fixedThisDay) { // Solo loguear el encabezado si no lo hemos hecho
                            $log[] = "\n[!] Empleado: {$employee->id} ({$employee->first_name}) - Día: $date";
                        }
                        $fixedThisDay = true;
                        $totalOrphansFixed++;

                        $startTime = Carbon::parse($lastBreakRecord->created_at);
                        // Creamos el 'break_end' 30 minutos después
                        $endTime = $startTime->copy()->addMinutes(30);

                        Attendance::create([
                            'employee_id' => $employee->id,
                            'type' => 'break_end',
                            'created_at' => $endTime, // Asignamos la nueva hora
                            'updated_at' => $endTime, // Asignamos la nueva hora
                        ]);
                        
                        $log[] = "    -> Se encontró 'break_start' huérfano. Se creó 'break_end' a las {$endTime->format('H:i:s')}.";
                    }
                }

                if ($fixedThisDay) {
                    $totalFixedDays++;
                }
            } // fin foreach $attendancesByDay
        } // fin foreach $employees
    }); // fin chunk

    // 3. MOSTRAR RESULTADO FINAL
    $log[] = "\n===================================================================";
    $log[] = "Limpieza completada.";
    $log[] = "Total de días-empleado con correcciones: $totalFixedDays";
    $log[] = "Total de descansos huérfanos reparados: $totalOrphansFixed";

    // Usamos <pre> para que los saltos de línea se vean bien en el navegador
    return "<pre>" . implode("\n", $log) . "</pre>";
});