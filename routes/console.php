<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

// 2. Programar el comando para que se ejecute todos los viernes a las 23:58.
Schedule::command('payroll:cycle')->weeklyOn(5, '23:58');