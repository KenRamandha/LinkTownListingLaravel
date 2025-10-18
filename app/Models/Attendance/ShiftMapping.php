<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftMapping extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'shifts_mapping';

    protected $fillable = [
        'id',
        'user_id',
        'shift_id',
        'work_date',
        'checkin_time',
        'late',
        'checkin_lat',
        'checkin_lng',
        'checkin_distance',
        'checkin_address',
        'checkin_photo',
        'checkin_note',
        'checkout_time',
        'early_checkout',
        'checkout_lat',
        'checkout_lng',
        'checkout_distance',
        'checkout_address',
        'checkout_photo',
        'checkout_note',
        'attendance_status',
        'lock_location',
        'proposed_checkin_time',
        'proposed_checkout_time',
        'description',
        'request_status',
        'request_file',
        'comment',
        'approved_by',
    ];

    protected $casts = [
        'work_date'         => 'date',
        'checkin_time'      => 'string',
        'late'              => 'int',
        'checkout_time'     => 'string',
        'early_checkout'    => 'int',
        'checkin_lat'       => 'float',
        'checkin_lng'       => 'float',
        'checkout_lat'      => 'float',
        'checkout_lng'      => 'float',
        'checkin_distance'  => 'float',
        'checkin_address'   => 'string',
        'checkout_distance' => 'float',
        'checkout_address'  => 'string',
        'lock_location'     => 'boolean',
        'proposed_checkin_time'  => 'string',
        'proposed_checkout_time' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Core\User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
