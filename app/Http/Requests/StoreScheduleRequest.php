<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:schedules,name'],
            'branch_ids' => 'present|array',
            'branch_ids.*' => 'exists:branches,id',
            'details' => 'required|array|size:7',
            'details.*.day_of_week' => 'required|integer|between:1,7',
            'details.*.is_active' => 'required|boolean',
            'details.*.start_time' => 'nullable|required_if:details.*.is_active,true|date_format:H:i',
            'details.*.end_time' => 'nullable|required_if:details.*.is_active,true|date_format:H:i|after:details.*.start_time',
            'details.*.meal_minutes' => 'nullable|required_if:details.*.is_active,true|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'details.*.start_time.required_if' => 'Llenar campo',
            'details.*.end_time.required_if' => 'Llenar campo',
            'details.*.end_time.after' => 'La hora de finalización debe ser posterior a la hora de inicio.',
        ];
    }
}
