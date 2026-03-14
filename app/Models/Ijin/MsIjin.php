<?php

namespace App\Models\Ijin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model MsIjin - Representasi tabel ms_ijin
 * Master table untuk konfigurasi jenis form ijin
 */
class MsIjin extends Model
{
    use HasFactory;

    protected $table = 'ms_ijin';

    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'jenis_form',
        'tanggal_dari',
        'tanggal_sampai',
        'tujuan',
        'remark1',
        'remark2',
        'note',
        'photo',
    ];

    protected $casts = [
        'tanggal_dari' => 'string',
        'tanggal_sampai' => 'string',
        'photo' => 'string',
    ];

    /**
     * Get all transaction ijin for this master ijin type
     */
    public function transactions()
    {
        return $this->hasMany(TrIjin::class, 'jenis_form', 'jenis_form');
    }
}
