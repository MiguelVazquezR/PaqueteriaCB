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
        Branch::create(['name' => 'Matriz Guadalajara', 'address' => 'Av. Vallarta 123, Guadalajara, Jalisco']);
        Branch::create(['name' => 'Sucursal Periférico', 'address' => 'Periférico Sur 456, Zapopan, Jalisco']);
        Branch::create(['name' => 'CEDIS Tlaquepaque', 'address' => 'Carretera a Chapala 789, Tlaquepaque, Jalisco']);
    }
}
