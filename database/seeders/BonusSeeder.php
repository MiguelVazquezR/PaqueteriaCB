<?php

namespace Database\Seeders;

use App\Models\Bonus;
use Illuminate\Database\Seeder;

class BonusSeeder extends Seeder
{
    public function run(): void
    {
        Bonus::create([
            'name' => 'Bono de Puntualidad',
            'type' => 'automatic',
            'amount' => 500.00,
            'rules' => [
                'type' => 'punctuality', // Identificador para la lógica de cálculo
                'threshold_minutes' => 15,
            ],
        ]);

        Bonus::create([
            'name' => 'Bono de Asistencia',
            'type' => 'automatic',
            'amount' => 800.00,
            'rules' => [
                'type' => 'attendance', // Identificador para la lógica de cálculo
                'threshold_absences' => 0,
                'unjustified_absence_type_id' => 1,
            ],
        ]);
    }
}