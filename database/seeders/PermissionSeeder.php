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
                'gestionar_incidencias', // Un permiso para crear, editar, eliminar, etc.
                'aprobar_incidencias',
            ],
            'nominas' => [
                'ver_nominas',
                'generar_nominas',
                'aprobar_nominas',
            ],
            'configuraciones' => [
                'gestionar_roles_permisos',
                'gestionar_configuraciones_generales',
            ],
            'reportes' => [
                'ver_reportes',
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
        $adminRole = Role::create(['name' => 'Administrador']);
        $adminRole->givePermissionTo(Permission::all());

        // Rol de Encargado de Sucursal
        $branchManagerRole = Role::create(['name' => 'Encargado de sucursal']);
        $branchManagerRole->givePermissionTo([
            'ver_usuarios',
            'editar_usuarios',
            'ver_incidencias',
            'gestionar_incidencias',
            'ver_reportes',
        ]);
        
        // Rol de Colaborador (permisos mínimos)
        $collaboratorRole = Role::create(['name' => 'Colaborador']);
        // Este rol podría tener permisos para ver sus propias incidencias, por ejemplo.
    }
}
