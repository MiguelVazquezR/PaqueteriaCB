<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::create([
            'name' => 'Matriz Guadalajara',
            'address' => 'Av. Vallarta 123, Guadalajara, Jalisco',
            'phone' => '3310203040',
            'settings' => ['timezone' => 'America/Mexico_City'],
            'business_hours' => [
                // La clave '1' representa Lunes-Viernes para agrupar, pero puedes separarlos si lo necesitas.
                '1' => ['day_name' => 'Lunes a Viernes', 'is_active' => true, 'start_time' => '09:00', 'end_time' => '19:00'],
                '6' => ['day_name' => 'Sábado', 'is_active' => true, 'start_time' => '10:00', 'end_time' => '14:00'],
                '7' => ['day_name' => 'Domingo', 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ]
        ]);

        Branch::create([
            'name' => 'Sucursal Periférico',
            'address' => 'Periférico Sur 456, Zapopan, Jalisco',
            'phone' => '3350607080',
            'settings' => ['timezone' => 'America/Mexico_City'],
            'business_hours' => [
                '1' => ['day_name' => 'Lunes a Viernes', 'is_active' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
                '6' => ['day_name' => 'Sábado', 'is_active' => true, 'start_time' => '09:00', 'end_time' => '13:00'],
                '7' => ['day_name' => 'Domingo', 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ]
        ]);
        
        Branch::create([
            'name' => 'CEDIS Tlaquepaque',
            'address' => 'Carretera a Chapala 789, Tlaquepaque, Jalisco',
            'phone' => '3390102030',
            'settings' => ['timezone' => 'America/Mexico_City'],
            'business_hours' => [
                '1' => ['day_name' => 'Lunes a Viernes', 'is_active' => true, 'start_time' => '08:00', 'end_time' => '18:00'],
                '6' => ['day_name' => 'Sábado', 'is_active' => false, 'start_time' => null, 'end_time' => null],
                '7' => ['day_name' => 'Domingo', 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ]
        ]);
    }
}