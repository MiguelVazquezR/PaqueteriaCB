<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'employee_number' => $this->employee_number,
            'position' => $this->position,
            'is_active' => (bool) $this->is_active,
            'phone' => $this->phone,
            'hire_date' => $this->hire_date,
            'birth_date' => $this->birth_date,
            'curp' => $this->curp,
            'rfc' => $this->rfc,
            'nss' => $this->nss,
            'base_salary' => $this->base_salary,
            'vacation_balance' => $this->vacation_balance,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'branch' => $this->whenLoaded('branch', fn() => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'schedules' => $this->whenLoaded('schedules', fn() =>
                $this->schedules->map(fn($schedule) => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'details' => $schedule->details,
                ])
            ),
            'vacation_history' => $this->whenLoaded('vacationLedger'),
        ];
    }
}