<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceAndIncidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::where('is_active', true)->get();
        $periods = PayrollPeriod::all();
        $incidentTypes = IncidentType::all();

        foreach ($employees as $employee) {
            foreach ($periods as $period) {
                $dateRange = CarbonPeriod::create($period->start_date, $period->end_date);

                foreach ($dateRange as $date) {
                    // 5% de probabilidad de tener una incidencia en un día laborable
                    if ($date->isWeekday() && rand(1, 100) <= 5) {
                        $randomIncidentType = $incidentTypes->whereNotIn('code', ['DESC', 'FESTIVO'])->random();
                        Incident::create([
                            'employee_id' => $employee->id,
                            'incident_type_id' => $randomIncidentType->id,
                            'start_date' => $date,
                            'end_date' => $date,
                            'status' => 'approved',
                        ]);
                        // Si es una incidencia de día completo, no generamos asistencia
                        continue; 
                    }

                    // Generar asistencia para días laborables sin incidencia
                    if ($date->isWeekday()) {
                        // Entrada (entre 8:45 y 9:15)
                        $entryTime = $date->copy()->setTime(9, 0, 0)->addMinutes(rand(-15, 15));
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'type' => 'entry',
                            'created_at' => $entryTime,
                        ]);

                        // Salida (entre 17:45 y 18:15)
                        $exitTime = $date->copy()->setTime(18, 0, 0)->addMinutes(rand(-15, 15));
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'type' => 'exit',
                            'created_at' => $exitTime,
                        ]);
                    }
                }
            }
        }
    }
}
