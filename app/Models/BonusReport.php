<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'status',
        'generated_at',
        'finalized_by_user_id',
        'finalized_at',
    ];

    protected $casts = [
        'period' => 'date',
        'generated_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    /**
     * Obtiene los detalles (bonos por empleado) de este reporte.
     */
    public function details(): HasMany
    {
        return $this->hasMany(BonusReportDetail::class);
    }

    /**
     * Obtiene el usuario que finalizó el reporte.
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }
}