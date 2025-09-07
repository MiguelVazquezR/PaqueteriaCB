<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'category' => 'required|string|max:255',
        ]);

        // Normalizar las cadenas a un formato snake_case sin espacios
        $action = Str::slug($validated['action'], '_');
        $category = Str::slug($validated['category'], '_');

        $permissionName = "{$action}_{$category}";

        // Validar que el permiso no exista ya
        $request->validate([
            'name' => Rule::unique('permissions')->where(function ($query) use ($permissionName) {
                return $query->where('name', $permissionName);
            }),
        ], [
            'name.unique' => 'Este permiso ya existe.',
        ]);

        Permission::create(['name' => $permissionName]);

        return back();
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'category' => 'required|string|max:255',
        ]);

        $action = Str::slug($validated['action'], '_');
        $category = Str::slug($validated['category'], '_');
        $newPermissionName = "{$action}_{$category}";

        // Validar que el nuevo nombre no exista ya, ignorando el permiso actual
        $request->validate(
            ['name' => Rule::unique('permissions')->ignore($permission->id)->where('name', $newPermissionName)],
            ['name.unique' => 'Este permiso ya existe.']
        );

        $permission->update(['name' => $newPermissionName]);

        return back()->with('success', 'Permiso actualizado exitosamente.');
    }

    public function destroy(Permission $permission)
    {
        // Opcional: Añadir lógica para prevenir la eliminación de permisos en uso
        $permission->delete();

        return back()->with('success', 'Permiso eliminado exitosamente.');
    }
}
