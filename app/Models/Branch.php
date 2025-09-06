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
        'business_hours',
    ];

    protected $casts = [
        'settings' => 'array',
        'business_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'branch_schedule');
    }
}
