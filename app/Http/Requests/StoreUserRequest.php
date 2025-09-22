<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se maneja con el middleware del controlador.
    }

    public function rules(): array
    {
        return [
            // Información Personal y Laboral
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'employee_number' => 'required|string|max:50|unique:employees',
            'hire_date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'position' => 'required|string|max:255',
            'curp' => 'nullable|string|max:18|unique:employees',
            'rfc' => 'nullable|string|max:13|unique:employees',
            'nss' => 'nullable|string|max:11|unique:employees',
            'base_salary' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'schedule_id' => 'required|exists:schedules,id',
            'termination_date' => 'required_if:is_active,false|nullable|date',
            'termination_reason' => 'required_if:is_active,false|nullable|string|max:255',

            // Contacto de Emergencia
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:255',

            // Acceso al sistema (condicional)
            'create_user_account' => 'required|boolean',
            'email' => ['required_if:create_user_account,true', 'nullable', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required_if:create_user_account,true', 'nullable', 'string', 'min:8'],
            'role_id' => ['required_if:create_user_account,true', 'nullable', 'exists:roles,id'],
            'facial_image' => ['nullable', 'image'], // 1MB max size
        ];
    }
}