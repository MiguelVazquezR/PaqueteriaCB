<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
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
            'name' => $this->name,
            // Carga condicional de la relación para un rendimiento óptimo.
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(fn($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ]);
            }),
        ];
    }
}
