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
        'image_path',
        'created_at',
        'late_minutes',
        'late_ignored',
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