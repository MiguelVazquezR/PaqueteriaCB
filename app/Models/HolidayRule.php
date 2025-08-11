<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayRule extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'name',
        'rule_definition',
    ];

    protected $casts = [
        'rule_definition' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function concreteHolidays()
    {
        return $this->hasMany(ConcreteHoliday::class);
    }
}