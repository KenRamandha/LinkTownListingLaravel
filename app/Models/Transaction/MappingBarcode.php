<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

// Model MappingBarcode - Representasi tabel mapping_barcode
class MappingBarcode extends Model
{
    protected $table = 'mapping_barcode';

    public $timestamps = false;

    protected $fillable = [
        'kode_barang',
        'kode_barcode',
        'flag',
        'updated_flag',
    ];

    protected $casts = [
        'updated_flag' => 'date',
    ];

    protected $attributes = [
        'flag' => '0',
    ];
}
