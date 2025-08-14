<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'rules',
        'amount',
    ];

    protected $casts = [
        'rules' => 'array',
        'amount' => 'decimal:2',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_bonus')
            ->withPivot('payroll_id', 'applied_amount')
            ->withTimestamps();
    }
}
