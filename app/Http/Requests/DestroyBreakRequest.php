<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_id' => 'required|exists:attendances,id',
            // Cambiado a nullable por si se requiere eliminar un descanso huérfano
            'end_id'   => 'nullable|exists:attendances,id', 
        ];
    }
}