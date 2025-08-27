<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function details()
    {
        return $this->hasMany(ScheduleDetail::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_schedule')
            ->withPivot('start_date', 'end_date')
            ->withTimestamps();
    }


    //Un horario puede estar vinculado a muchas sucursales.
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_schedule');
    }
}