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