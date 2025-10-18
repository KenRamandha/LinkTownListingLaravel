<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'leave_requests';

    protected $fillable = [
        'id',
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approver_id',
        'approved_at',
        'rejected_at',
        'comment',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'approver_id');
    }

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
