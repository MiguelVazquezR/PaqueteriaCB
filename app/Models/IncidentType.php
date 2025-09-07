<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentType extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
    ];

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}