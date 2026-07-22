<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year_number',
        'period_start',
        'period_end',
        'days_entitled',
        'days_accrued',
        'days_taken',
        'is_premium_paid',
        'premium_paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'premium_paid_at' => 'datetime',
        'is_premium_paid' => 'boolean',
        'days_entitled' => 'decimal:4',
        'days_accrued' => 'decimal:4',
        'days_taken' => 'decimal:4',
    ];

    /**
     * El empleado al que pertenece este periodo.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Días restantes disponibles EXCLUSIVAMENTE de este periodo.
     */
    protected function remainingDays(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->days_accrued - $this->days_taken),
        );
    }

    /**
     * Determina si el periodo está "Agotado" (se tomaron todos los días a los que tenía derecho).
     * Esto servirá para mostrar la alerta de "Pagar Prima".
     */
    protected function isExhausted(): Attribute
    {
        return Attribute::make(
            // Consideramos agotado si lo tomado es mayor o igual a lo que le tocaba (con un pequeño margen de error por decimales)
            get: fn () => $this->days_taken >= ($this->days_entitled - 0.01),
        );
    }
}