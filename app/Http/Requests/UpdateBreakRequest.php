<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBreakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => 'required|date_format:Y-m-d',
            'start_id'   => 'required|exists:attendances,id',
            // Cambiado a nullable para permitir actualizar descansos sin 'end_id' previo
            'end_id'     => 'nullable|exists:attendances,id', 
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
        ];
    }
}