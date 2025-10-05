<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    public $incrementing=false; protected $keyType='string';
    protected $table='attendance_logs';
}
