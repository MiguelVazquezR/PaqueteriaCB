<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear un horario general por defecto
        $generalSchedule = Schedule::create([
            'name' => 'Horario General (9am - 6pm)',
        ]);

        // 2. Definir los detalles de ese horario
        $scheduleDetails = [
            // Lunes (1) a Viernes (5)
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'meal_minutes' => 60],
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'meal_minutes' => 60],
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'meal_minutes' => 60],
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'meal_minutes' => 60],
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'meal_minutes' => 60],
            // Sábado (6)
            ['schedule_id' => $generalSchedule->id, 'day_of_week' => 6, 'start_time' => '09:00:00', 'end_time' => '14:00:00', 'meal_minutes' => 0],
        ];

        DB::table('schedule_details')->insert($scheduleDetails);

        // 3. Asignar este horario a TODOS los empleados
        $employees = Employee::all();
        foreach ($employees as $employee) {
            // Usamos attach para crear el registro en la tabla pivote 'employee_schedule'
            $employee->schedules()->attach($generalSchedule->id, [
                'start_date' => $employee->hire_date, // El horario aplica desde su contratación
                'end_date' => null, // No tiene fecha de fin
            ]);
        }
    }
}
