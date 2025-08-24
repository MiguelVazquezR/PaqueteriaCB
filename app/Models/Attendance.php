<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'created_by_user_id',
        'type',
        'late_minutes',
        'late_ignored',
        'image_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}