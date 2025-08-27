<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index()
    {
        // Obtener todos los roles con el número de permisos que tienen asignados
        $roles = Role::with('permissions:name')->withCount('permissions')->get();

        // Obtener todos los permisos y agruparlos por módulo
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Extrae el módulo del nombre del permiso (ej. "view_users" -> "users")
            return explode('_', $permission->name)[1] ?? 'general';
        });

        return Inertia::render('Setting/RolePermission/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function create()
    {
        // Obtener todos los permisos y agruparlos por módulo
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('_', $permission->name)[1] ?? 'general';
        });

        return Inertia::render('Setting/RolePermission/Create', [
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'present|array', // Asegura que el array de permisos esté presente
            'permissions.*' => 'string|exists:permissions,name', // Valida que cada permiso exista
        ]);

        // Crear el nuevo rol
        $role = Role::create(['name' => $validated['name']]);

        // Asignar los permisos seleccionados al nuevo rol
        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        return redirect()->route('settings.roles-permissions.index');
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Sincroniza los permisos (elimina los viejos y añade los nuevos)
        $role->syncPermissions($validated['permissions']);

        return back()->with('success', 'Permisos actualizados correctamente.');
    }
}
