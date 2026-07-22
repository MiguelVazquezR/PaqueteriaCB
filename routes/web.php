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
use App\Models\VacationLedger;
use App\Models\VacationPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

// --- RUTA MAESTRA: SINCRONIZAR PERIODOS DE VACACIONES Y PRIMAS ---
Route::get('/admin/sync-vacation-periods', function () {
    
    // --- FUNCIÓN HELPER PARA LA LEY DE VACACIONES ---
    $getEntitledDays = function ($year) {
        if ($year <= 0) return 0;
        if ($year == 1) return 12;
        if ($year == 2) return 14;
        if ($year == 3) return 16;
        if ($year == 4) return 18;
        if ($year == 5) return 20;
        if ($year >= 6 && $year <= 10) return 22;
        if ($year >= 11 && $year <= 15) return 24;
        if ($year >= 16 && $year <= 20) return 26;
        if ($year >= 21 && $year <= 25) return 28;
        if ($year >= 26 && $year <= 30) return 30;
        return 32;
    };

    $log = [];
    $log[] = "INICIANDO SINCRONIZACIÓN DE PERIODOS VACACIONALES...";
    $log[] = "===================================================";
    $log[] = "MODO INTELIGENTE: Años pasados se cierran automáticamente. Año actual usa historial real.";

    // Obtenemos empleados activos y sus datos
    $employees = Employee::where('is_active', true)->get();

    foreach ($employees as $employee) {
        $log[] = "\nProcesando Empleado: {$employee->first_name} {$employee->last_name} (#{$employee->employee_number})";
        $log[] = "Fecha de Ingreso: " . $employee->hire_date->toDateString();

        $yearsOfService = $employee->hire_date->diffInYears(now());
        
        // Iteramos desde el año 0 (primer año) hasta el año actual
        for ($i = 0; $i <= $yearsOfService; $i++) {
            $yearNumber = $i + 1;
            $periodStart = $employee->hire_date->copy()->addYears($i);
            $periodEnd = $employee->hire_date->copy()->addYears($i + 1)->subDay();
            $daysEntitled = $getEntitledDays($yearNumber);

            // Determinar si es un periodo pasado o el actual
            $isPastPeriod = $periodEnd->isPast();
            
            // --- LÓGICA DE DEVENGADO (ACCRUED) ---
            // Si es pasado, ya devengó todo. Si es actual, proporcional.
            $daysAccrued = $isPastPeriod 
                ? $daysEntitled 
                : ($daysEntitled / 365) * $periodStart->diffInDays(now());
            
            // Topeamos accrued a entitled para no generar confusión visual por decimales extra
            $daysAccrued = min($daysAccrued, $daysEntitled);

            // --- LÓGICA DE TOMADO (TAKEN) ---
            $daysTaken = 0;

            if ($isPastPeriod) {
                // CASO 1: AÑO PASADO (MIGRACIÓN/CIERRE FORZOSO)
                // Como el sistema es nuevo, asumimos que todos los años anteriores ya fueron consumidos/pagados.
                $daysTaken = $daysEntitled;
            } else {
                // CASO 2: AÑO ACTUAL/FUTURO
                // Aquí usamos el historial REAL (Ledger), filtrando solo los registros que caen en ESTE periodo.
                $daysTaken = VacationLedger::where('employee_id', $employee->id)
                    ->where('type', 'taken')
                    ->whereDate('date', '>=', $periodStart)
                    ->whereDate('date', '<=', $periodEnd)
                    ->sum(DB::raw('ABS(days)')); // Usamos ABS porque se guardan en negativo
            }

            // Crear o actualizar el periodo
            $period = VacationPeriod::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year_number' => $yearNumber
                ],
                [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'days_entitled' => $daysEntitled,
                    'days_accrued' => $daysAccrued,
                    'days_taken' => $daysTaken, // Guardamos el cálculo
                ]
            );

            // --- LÓGICA DE PRIMA VACACIONAL ---
            // Si el año es pasado, asumimos prima pagada para no generar alertas falsas.
            if ($isPastPeriod && !$period->is_premium_paid) {
                $period->update([
                    'is_premium_paid' => true,
                    'premium_paid_at' => $periodEnd, // Usamos fecha fin de periodo como referencia
                ]);
                $log[] = "   - Año {$yearNumber} (Pasado): CERRADO AUTOMÁTICAMENTE (Tomados: {$daysTaken}/{$daysEntitled}) y Prima Pagada.";
            } elseif (!$isPastPeriod) {
                // Para el año actual, solo informamos
                $log[] = "   - Año {$yearNumber} (ACTUAL): En curso. Tomados: {$daysTaken} (Según historial). Devengados: " . number_format($daysAccrued, 2);
            }
        }
    }

    $log[] = "\n===================================================";
    $log[] = "PROCESO COMPLETADO.";
    return "<pre>" . implode("\n", $log) . "</pre>";
});

// --- NUEVA RUTA: CORREGIR DESFASE HORARIO (RESTAR 1 HORA) ---
// ATENCIÓN: Solo debes ejecutar esto UNA VEZ en el navegador visitando /admin/fix-time-offset
Route::get('/admin/fix-time-offset', function () {
    $log = [];
    $log[] = "INICIANDO CORRECCIÓN DE ZONA HORARIA (-1 HORA)...";
    $log[] = "===================================================================";
    
    // Obtenemos el año actual automáticamente para generar las fechas
    $year = now()->year;
    
    // Definimos los rangos de fechas afectados donde hubo desfase
    $ranges = [
        // Del 8 al 18 de Marzo (ambos incluidos, cubriendo todo el día)
        ['start' => "$year-03-08 00:00:00", 'end' => "$year-03-18 23:59:59"],
        // El día 20 de Marzo (cubriendo todo el día)
        ['start' => "$year-03-20 00:00:00", 'end' => "$year-03-20 23:59:59"],
    ];

    $totalFixed = 0;

    foreach ($ranges as $range) {
        $log[] = "\nBuscando registros afectados entre {$range['start']} y {$range['end']}...";

        // Obtenemos las asistencias registradas dentro de esos intervalos
        $attendances = Attendance::whereBetween('created_at', [$range['start'], $range['end']])->get();

        if ($attendances->isEmpty()) {
            $log[] = "  -> No se encontraron registros en este rango temporal.";
            continue;
        }

        foreach ($attendances as $attendance) {
            // Guardamos la hora original parseando con Carbon explícitamente para evitar errores de tipo string
            $originalTime = \Carbon\Carbon::parse($attendance->created_at)->format('Y-m-d H:i:s');

            // Calculamos y guardamos las nuevas fechas en variables
            $newCreatedAt = \Carbon\Carbon::parse($attendance->created_at)->subHour();
            $newUpdatedAt = \Carbon\Carbon::parse($attendance->updated_at)->subHour();

            // Las reasignamos
            $attendance->created_at = $newCreatedAt;
            $attendance->updated_at = $newUpdatedAt;

            // Esto es crucial: le decimos a Eloquent que no actualice el 'updated_at' 
            // a la hora de AHORA MISMO, sino que respete el cálculo de arriba
            $attendance->timestamps = false;
            $attendance->save();

            $totalFixed++;
            
            // Usamos nuestra variable segura de Carbon ($newCreatedAt) para el log final
            $log[] = "  [Ajustado] ID: {$attendance->id} | {$originalTime}  =>  {$newCreatedAt->format('Y-m-d H:i:s')}";
        }
    }

    $log[] = "\n===================================================================";
    $log[] = "PROCESO COMPLETADO EXITOSAMENTE.";
    $log[] = "Total de registros de asistencia retrocedidos 1 hora: $totalFixed";

    return "<pre>" . implode("\n", $log) . "</pre>";
});

// --- NUEVA RUTA: CORREGIR DESFASE HORARIO (SUMAR 1 HORA DEL 7 AL 31 DE MARZO) ---
// ATENCIÓN: Solo debes ejecutar esto UNA VEZ en el navegador visitando /admin/fix-time-add-hour
Route::get('/admin/fix-time-add-hour', function () {
    $log = [];
    $log[] = "INICIANDO CORRECCIÓN DE ZONA HORARIA (+1 HORA)...";
    $log[] = "===================================================================";
    
    // Obtenemos el año actual
    $year = now()->year;
    
    // Rango afectado: Del 7 al 31 de Marzo
    $range = ['start' => "$year-03-07 00:00:00", 'end' => "$year-03-31 23:59:59"];

    $totalFixed = 0;

    $log[] = "\nBuscando registros afectados entre {$range['start']} y {$range['end']}...";

    // Obtenemos las asistencias registradas dentro de ese intervalo
    $attendances = Attendance::whereBetween('created_at', [$range['start'], $range['end']])->get();

    if ($attendances->isEmpty()) {
        $log[] = "  -> No se encontraron registros en este rango temporal.";
    } else {
        foreach ($attendances as $attendance) {
            // Guardamos la hora original
            $originalTime = \Carbon\Carbon::parse($attendance->created_at)->format('Y-m-d H:i:s');

            // Sumamos 1 hora a los timestamps
            $newCreatedAt = \Carbon\Carbon::parse($attendance->created_at)->addHour();
            $newUpdatedAt = \Carbon\Carbon::parse($attendance->updated_at)->addHour();

            // Reasignamos las nuevas fechas
            $attendance->created_at = $newCreatedAt;
            $attendance->updated_at = $newUpdatedAt;

            // Evitamos que Eloquent pise el updated_at con la hora actual de la ejecución
            $attendance->timestamps = false;
            $attendance->save();

            $totalFixed++;
            
            // Log de la operación para visualizar los cambios
            $log[] = "  [Ajustado] ID: {$attendance->id} | {$originalTime}  =>  {$newCreatedAt->format('Y-m-d H:i:s')}";
        }
    }

    $log[] = "\n===================================================================";
    $log[] = "PROCESO COMPLETADO EXITOSAMENTE.";
    $log[] = "Total de registros de asistencia adelantados 1 hora: $totalFixed";

    return "<pre>" . implode("\n", $log) . "</pre>";
});