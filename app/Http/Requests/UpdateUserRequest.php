<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $employee = $user->employee;

        return [
            // Información Personal y Laboral
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($employee?->id)],
            'hire_date' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'position' => 'required|string|max:255',
            'curp' => ['nullable', 'string', 'max:18', Rule::unique('employees')->ignore($employee?->id)],
            'rfc' => ['nullable', 'string', 'max:13', Rule::unique('employees')->ignore($employee?->id)],
            'nss' => ['nullable', 'string', 'max:11', Rule::unique('employees')->ignore($employee?->id)],
            'base_salary' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'schedule_id' => 'required|exists:schedules,id',
            'termination_date' => 'required_if:is_active,false|nullable|date',
            'termination_reason' => 'required_if:is_active,false|nullable|string|max:255',

            // Contacto de Emergencia
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:50',

            // Acceso al sistema
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'], // La contraseña es opcional al editar
            'role_id' => ['required', 'exists:roles,id'],
            'facial_image' => ['nullable', 'image'],
            'delete_photo' => 'boolean',
        ];
    }
}