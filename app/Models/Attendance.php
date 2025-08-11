<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    // Laravel manejará created_at y updated_at automáticamente.
    // Si solo usas created_at, define: const UPDATED_AT = null;
    
    protected $fillable = [
        'employee_id',
        'created_by_user_id',
        'type',
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