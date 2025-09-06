<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Definir los permisos para cada módulo en español
        $permissions = [
            'usuarios' => [
                'ver_usuarios',
                'crear_usuarios',
                'editar_usuarios',
                'eliminar_usuarios',
            ],
            'sucursales' => [
                'ver_sucursales',
                'crear_sucursales',
                'editar_sucursales',
                'eliminar_sucursales',
            ],
            'horarios' => [
                'ver_horarios',
                'crear_horarios',
                'editar_horarios',
                'eliminar_horarios',
            ],
            'incidencias' => [
                'ver_incidencias',
                'gestionar_incidencias',
            ],
            'bonos' => [
                'ver_bonos',
                'finalizar_bonos',
            ],
            'roles' => [
                'ver_roles_permisos',
                'crear_roles',
                'editar_roles',
                'eliminar_roles',
            ],
            'permisos' => [
                'crear_permisos',
                'editar_permisos',
                'eliminar_permisos',
            ],
            'festivos' => [
                'ver_festivos',
                'crear_festivos',
                'editar_festivos',
                'eliminar_festivos',
            ],
        ];

        // Crear los permisos
        foreach ($permissions as $module => $permissionList) {
            foreach ($permissionList as $permission) {
                Permission::create(['name' => $permission]);
            }
        }

        // --- Crear Roles y Asignar Permisos ---

        // Rol de Administrador (acceso total)
        $adminRole = Role::create(['name' => 'Super administrador']);
        $adminRole->givePermissionTo(Permission::all());
    }
}
