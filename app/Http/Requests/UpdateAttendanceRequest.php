<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'entry_time' => 'nullable|date_format:H:i',
            'exit_time' => [
                'nullable',
                'date_format:H:i',
                // Solo valida 'after_or_equal' si el campo 'entry_time' está presente en la solicitud
                Rule::when($this->filled('entry_time'), 'after_or_equal:entry_time')
            ],
        ];
    }
}