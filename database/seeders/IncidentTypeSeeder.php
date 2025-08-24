<?php

namespace Database\Seeders;

use App\Models\IncidentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncidentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Falta injustificada', 'code' => 'F_INJUST'],
            ['name' => 'Falta justificada', 'code' => 'F_JUST'],
            ['name' => 'Retardo', 'code' => 'RETARDO'],
            ['name' => 'Permiso con goce', 'code' => 'P_GOCE'],
            ['name' => 'Permiso sin goce', 'code' => 'P_SIN_GOCE'],
            ['name' => 'Incapacidad general', 'code' => 'INC_GRAL'],
            ['name' => 'Incapacidad por trabajo', 'code' => 'INC_TRAB'],
            ['name' => 'Vacaciones', 'code' => 'VAC'],
            ['name' => 'Día Festivo', 'code' => 'FESTIVO'],
            ['name' => 'Descanso', 'code' => 'DESC'],
        ];

        foreach ($types as $type) {
            IncidentType::create($type);
        }
    }
}
