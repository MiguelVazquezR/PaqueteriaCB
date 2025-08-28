<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'period_date',
        'total_late_minutes',
        'total_unjustified_absences',
        'punctuality_bonus_earned',
        'attendance_bonus_earned',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_date' => 'date',
        'punctuality_bonus_earned' => 'boolean',
        'attendance_bonus_earned' => 'boolean',
    ];

    /**
     * Get the employee that owns the bonus report.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
