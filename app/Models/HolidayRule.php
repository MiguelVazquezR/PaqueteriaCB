<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayRule extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'rule_definition',
        'is_active',
    ];

    protected $casts = [
        'rule_definition' => 'array',
    ];
}
