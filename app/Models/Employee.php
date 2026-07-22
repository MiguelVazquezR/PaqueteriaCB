<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'phone',
        'birth_date',
        'address',
        'curp',
        'rfc',
        'nss',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'is_active',
        'vacation_balance', // Seguiremos usando esto como el "Total Global" para consultas rápidas
        'termination_date',
        'termination_reason',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
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

    /**
     * Scope a query to only include employees active within a given date range.
     */
    public function scopeActiveDuring(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->where('is_active', true)
            ->where('hire_date', '<=', $endDate)
            ->where(function ($subQuery) use ($startDate) {
                $subQuery->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $startDate);
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

    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonus')
            ->withPivot('payroll_id', 'applied_amount')
            ->withTimestamps();
    }

    /**
     * Get the vacation ledger history for the employee.
     */
    public function vacationLedger(): HasMany
    {
        return $this->hasMany(VacationLedger::class)->orderBy('date')->orderBy('id');
    }

    /**
     * Relación con los periodos de vacaciones (años de servicio).
     * Esto nos permitirá separar año con año.
     */
    public function vacationPeriods(): HasMany
    {
        return $this->hasMany(VacationPeriod::class)->orderBy('year_number');
    }

    /**
     * Get all period-specific notes for the employee.
     */
    public function periodNotes()
    {
        return $this->hasMany(EmployeePeriodNote::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['first_name'] . ' ' . $attributes['last_name'],
        );
    }
}