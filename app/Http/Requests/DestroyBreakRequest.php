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
            'end_id'   => 'required|exists:attendances,id',
        ];
    }
}