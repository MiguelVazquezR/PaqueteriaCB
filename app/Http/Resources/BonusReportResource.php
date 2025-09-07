<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BonusReportResource extends JsonResource
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
            'period' => $this->period, // Se mantiene el objeto Carbon para el frontend
            'status' => $this->status,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'finalized_by_user_id' => $this->finalized_by_user_id,
            // Puedes añadir cualquier otro dato que necesites en el frontend
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}