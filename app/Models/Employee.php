<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'employee_number',
        'first_name',
        'last_name',
        'position',
        'hire_date',
        'base_salary',
        'aws_rekognition_face_id',
        'is_active',
        'phone',
        'birth_date',
        'address',
        'curp',
        'rfc',
        'nss',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'base_salary' => 'decimal:2',
    ];

    /**
     * Scope a query to only include employees active within a given period.
     */
    public function scopeActiveInPeriod($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereHas('attendances', function ($subQ) use ($startDate, $endDate) {
                $subQ->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]);
            })
                ->orWhereHas('incidents', function ($subQ) use ($startDate, $endDate) {
                    $subQ->whereDate('start_date', '<=', $endDate)
                        ->whereDate('end_date', '>=', $startDate);
                });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'employee_schedule')
            ->withPivot('start_date', 'end_date')
            ->withTimestamps();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonus')
            ->withPivot('payroll_id', 'applied_amount')
            ->withTimestamps();
    }
}
