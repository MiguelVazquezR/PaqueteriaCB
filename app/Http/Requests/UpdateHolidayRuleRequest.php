<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $holidayId = $this->route('holiday')->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('holiday_rules')->ignore($holidayId)],
            'is_custom' => 'required|boolean',
            'rule_definition' => 'required|array',
            'applies_to_all' => 'required|boolean',
            'branch_ids' => 'required_if:applies_to_all,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'is_active' => 'required|boolean',
        ];
    }
}
