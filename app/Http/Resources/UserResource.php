<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EmployeeResource; // ¡Importante!

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // El nombre a mostrar será el del empleado si existe, si no, el del usuario.
        $displayName = $this->employee
            ? trim($this->employee->first_name . ' ' . $this->employee->last_name)
            : $this->name;

        return [
            // --- Datos del Usuario (siempre presentes) ---
            'id' => $this->id,
            'name' => $displayName, // Un solo campo `name` para simplificar el frontend.
            'email' => $this->email,
            'roles' => $this->whenLoaded('roles', fn() => $this->getRoleNames()->toArray()),
            'avatar_url' => $this->profile_photo_path
                ? Storage::url($this->profile_photo_path)
                : 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&color=700DBC&background=D8BBFC',

            // --- Datos del Empleado ---
            'employee' => $this->whenLoaded('employee', fn() =>
                $this->employee ? new EmployeeResource($this->resource->employee) : null
            ),
        ];
    }
}