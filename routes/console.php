<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

// Programar el comando para que se ejecute todos los viernes a las 23:00.
Schedule::command('payroll:cycle')->weeklyOn(5, '23:00');

// Generar el reporte preliminar de bonos
// Se ejecutará a las 11 PM del último día de cada mes.
Schedule::call(function () {
    // Obtenemos el mes actual en el formato 'Y-m' (ej. '2025-09')
    $currentMonth = now()->format('Y-m');

    // Invocamos el comando pasando el mes actual como opción.
    Artisan::call('bonuses:generate', [
        '--month' => $currentMonth
    ]);
})->lastDayOfMonth('23:00');

// Devengar días de vacaciones proporcionales para empleados activos.
// Se ejecutará cada sábado a la 1:00 AM.
Schedule::command('vacations:accrue')->weeklyOn(6, '1:00');