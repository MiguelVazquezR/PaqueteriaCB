<?php

use App\Console\Commands\GenerateBonusReport;
use Illuminate\Support\Facades\Schedule;

// 2. Programar el comando para que se ejecute todos los viernes a las 23:58.
Schedule::command('payroll:cycle')->weeklyOn(5, '23:58');

// Generar el reporte preliminar de bonos
// Se ejecutará a las 11 PM del último día de cada mes.
Schedule::command(GenerateBonusReport::class)->lastDayOfMonth('23:00');