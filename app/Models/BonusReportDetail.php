<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusReportDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonus_report_id',
        'employee_id',
        'bonus_id',
        'calculated_amount',
        'calculation_details',
    ];

    protected $casts = [
        'calculation_details' => 'json',
    ];

    /**
     * Obtiene el reporte principal al que pertenece este detalle.
     */
    public function bonusReport(): BelongsTo
    {
        return $this->belongsTo(BonusReport::class);
    }

    /**
     * Obtiene el empleado asociado a este bono.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }
}
