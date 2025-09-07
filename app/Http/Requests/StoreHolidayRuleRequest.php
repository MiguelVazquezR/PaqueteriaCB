<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:holiday_rules,name',
            'is_custom' => 'required|boolean',
            'rule_definition' => 'required|array',
            // Puedes añadir reglas más específicas para 'rule_definition' si es necesario
            // 'rule_definition.day' => 'required_if:is_custom,false|...',
            'applies_to_all' => 'required|boolean',
            'branch_ids' => 'required_if:applies_to_all,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'is_active' => 'required|boolean',
        ];
    }
}
