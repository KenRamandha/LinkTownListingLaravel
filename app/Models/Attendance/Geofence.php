<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model Geofence - Representasi tabel geofences
class Geofence extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'geofences';

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'latitude',
        'longitude',
        'radius_m',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'radius_m'  => 'int',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }
}
