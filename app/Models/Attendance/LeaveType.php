<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'leave_types';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'description',
        'max_days',
        'is_paid',
        'is_active',
    ];

    protected $casts = [
        'max_days'  => 'int',
        'is_paid'   => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
