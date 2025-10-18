<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'overtime_requests';

    protected $fillable = [
        'id',
        'user_id',
        'work_date',
        'start_time',
        'end_time',
        'reason',
        'status',
        'approver_id',
        'approved_at',
        'rejected_at',
        'comment',
    ];

    protected $casts = [
        'work_date'   => 'date',
        'start_time'  => 'datetime:H:i:s',
        'end_time'    => 'datetime:H:i:s',
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
}
