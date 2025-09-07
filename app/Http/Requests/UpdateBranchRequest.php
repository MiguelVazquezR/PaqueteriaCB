<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
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
        // Para este caso, las reglas son idénticas a las de creación.
        // Si hubiera un campo `unique` como un código de sucursal, aquí se añadiría ->ignore($this->branch->id).
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'settings' => 'required|array',
            'settings.timezone' => 'required|string',
            'is_active' => 'boolean',
            'business_hours' => 'required|array',
        ];
    }
}