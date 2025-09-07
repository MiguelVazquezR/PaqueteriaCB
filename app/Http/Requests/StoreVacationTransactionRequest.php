<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacationTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización se puede manejar aquí o a través del middleware del controlador.
        // Por ahora, permitimos que cualquiera con acceso a la ruta continúe.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:earned,taken,adjustment',
            'days' => ['required_unless:type,taken', 'nullable', 'numeric'],
            'start_date' => ['required_if:type,taken', 'nullable', 'date'],
            'end_date' => ['required_if:type,taken', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => 'nullable|string|max:255',
        ];
    }
}