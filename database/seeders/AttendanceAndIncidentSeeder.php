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
        // Cargamos los empleados con sus horarios para eficiencia
        $employees = Employee::where('is_active', true)->with('schedules.details')->get();
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
                        continue;
                    }

                    // Generar asistencia para días laborables sin incidencia
                    if ($date->isWeekday()) {
                        $entryTime = $date->copy()->setTime(9, 0, 0);
                        $lateMinutes = null;

                        if (rand(1, 100) <= 15) { // 15% de probabilidad de retardo
                            $minutes = rand(1, 30);
                            $entryTime->addMinutes($minutes);
                            $lateMinutes = $minutes; // Guardamos los minutos de retardo
                        } else {
                            $entryTime->subMinutes(rand(0, 15));
                        }

                        // Creamos el registro de entrada con los minutos de retardo ya calculados
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'type' => 'entry',
                            'created_at' => $entryTime,
                            'late_minutes' => $lateMinutes, // Se añade el valor aquí
                        ]);

                        // 80% de probabilidad de tomar un descanso para comer
                        if (rand(1, 100) <= 80) {
                            // El descanso empieza entre las 13:00 y las 14:00
                            $breakStartTime = $date->copy()->setTime(13, 0, 0)->addMinutes(rand(0, 60));
                            Attendance::create([
                                'employee_id' => $employee->id,
                                'type' => 'break_start',
                                'created_at' => $breakStartTime,
                            ]);

                            // El descanso dura entre 30 y 60 minutos
                            $breakEndTime = $breakStartTime->copy()->addMinutes(rand(30, 60));
                            Attendance::create([
                                'employee_id' => $employee->id,
                                'type' => 'break_end',
                                'created_at' => $breakEndTime,
                            ]);
                        }

                        // Salida
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
