<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear el Super Administrador (sin perfil de empleado)
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('321321321'),
        ]);

        // 2. Crear 25 usuarios con sus perfiles de empleado asociados
        // La factory se encargará de crear el empleado gracias al método `configure()`
        User::factory(25)->create();

        // 3. Asignar el Face ID de prueba a un empleado para poder testear.
        $firstEmployee = Employee::first();
        if ($firstEmployee) {
            $firstEmployee->aws_rekognition_face_id = 'example-face-id-12345';
            $firstEmployee->save();
        }
    }
}
