<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear el Usuario Administrador y su perfil de Empleado asociado
        $adminUser = User::factory()->create([
            'name' => 'Soporte DTW',
            'email' => 'contacto@dtw.com.mx',
            'password' => '321321321',
        ]);
        // Asignar rol usando el nombre exacto de tu PermissionSeeder
        $adminUser->assignRole('Super administrador');

        // $adminEmployee = Employee::create([
        //     'user_id' => $adminUser->id,
        //     'branch_id' => 1, // Asignado a la primera sucursal
        //     'first_name' => 'Admin',
        //     'last_name' => 'User',
        //     'employee_number' => 'ADM-001',
        //     'position' => 'Gerente General',
        //     'hire_date' => now()->subYear(),
        //     'base_salary' => 50000,
        //     'is_active' => true,
        // ]);
        // // Asignar el horario 'Administrativo' (ID 1 del ScheduleSeeder)
        // $adminEmployee->schedules()->attach(1, ['start_date' => now()]);


        // 2. Crear un Empleado de prueba (Colaborador) para testear fichaje
        // $testUser = User::factory()->create([
        //     'name' => 'Empleado de Prueba',
        //     'email' => 'empleado@example.com',
        //     'password' => 'password',
        // ]);
        // // Asignar rol usando el nombre exacto de tu PermissionSeeder
        // $testUser->assignRole('Colaborador');

        // $testEmployee = Employee::create([
        //     'user_id' => $testUser->id,
        //     'branch_id' => 2, // Asignado a la segunda sucursal
        //     'first_name' => 'Empleado',
        //     'last_name' => 'de Prueba',
        //     'employee_number' => 'EMP-001',
        //     'position' => 'Ventas',
        //     'hire_date' => now()->subMonths(6),
        //     'base_salary' => 15000,
        //     'is_active' => true,
        //     'aws_rekognition_face_id' => 'example-face-id-12345', // Face ID para pruebas
        // ]);
        // // Asignar el horario 'Operativo' (ID 2 del ScheduleSeeder)
        // $testEmployee->schedules()->attach(2, ['start_date' => now()]);

        // // 3. Crear un Empleado SIN cuenta de usuario para probar la edición
        // $employeeOnly = Employee::create([
        //     'user_id' => null,
        //     'branch_id' => 3, // Asignado a la tercera sucursal
        //     'first_name' => 'Ana',
        //     'last_name' => 'García',
        //     'employee_number' => 'OPE-002',
        //     'position' => 'Almacenista',
        //     'hire_date' => now()->subMonths(3),
        //     'base_salary' => 12000,
        //     'is_active' => true,
        // ]);
        // // Asignar el horario 'Operativo' (ID 2 del ScheduleSeeder)
        // $employeeOnly->schedules()->attach(2, ['start_date' => now()]);
    }
}