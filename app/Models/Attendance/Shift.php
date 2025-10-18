<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'shifts';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'start_time',
        'end_time',
        'timezone',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time'   => 'datetime:H:i:s',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function mappings()
    {
        return $this->hasMany(ShiftMapping::class);
    }
}
