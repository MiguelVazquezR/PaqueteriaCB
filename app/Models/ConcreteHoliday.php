<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConcreteHoliday extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'holiday_rule_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function holidayRule()
    {
        return $this->belongsTo(HolidayRule::class);
    }
}