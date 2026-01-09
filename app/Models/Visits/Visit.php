<?php

namespace App\Models\Visits;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model Visit - Representasi tabel kunjungan
class Visit extends Model
{
    use HasFactory;

    protected $table = 'kunjungan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'tanggal',
        'visit_in',
        'foto_in',
        'lat_in',
        'long_in',
        'address_in',
        'keterangan_in',
        'visit_out',
        'foto_out',
        'lat_out',
        'long_out',
        'address_out',
        'keterangan_out',
        'status',
    ];

    protected $casts = [
        'tanggal'   => 'date',
        'visit_in'  => 'datetime',
        'visit_out' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

