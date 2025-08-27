<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function holidayRules()
    {
        return $this->belongsToMany(HolidayRule::class, 'branch_holiday_rule');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'branch_schedule');
    }
}
