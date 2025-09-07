<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Horario Administrativo (Lunes a Viernes)
        $administrative = Schedule::create(['name' => 'Horario Administrativo']);
        $administrative->details()->createMany([
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00', 'meal_minutes' => 60],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00', 'meal_minutes' => 60],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '18:00', 'meal_minutes' => 60],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '18:00', 'meal_minutes' => 60],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '18:00', 'meal_minutes' => 60],
        ]);

        // Horario Operativo (L-V y Sábado medio día)
        $operative = Schedule::create(['name' => 'Turno Operativo']);
        $operative->details()->createMany([
            ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'meal_minutes' => 60],
            ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '17:00', 'meal_minutes' => 60],
            ['day_of_week' => 3, 'start_time' => '08:00', 'end_time' => '17:00', 'meal_minutes' => 60],
            ['day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '17:00', 'meal_minutes' => 60],
            ['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '17:00', 'meal_minutes' => 60],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '14:00', 'meal_minutes' => 0],
        ]);
    }
}

