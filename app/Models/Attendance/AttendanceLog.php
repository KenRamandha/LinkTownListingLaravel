<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'attendance_logs';

    protected $fillable = [
        'id',
        'user_id',
        'work_date',
        'type',
        'latitude',
        'longitude',
        'photo_url',
        'video_url',
        'device_info',
        'geofence_id',
        'note',
        'address',
        'logged_at',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'logged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }
}
