<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

// Model MappingProduk - Representasi tabel mapping_produk
class MappingProduk extends Model
{
    protected $table = 'mapping_produk';

    protected $primaryKey = 'kode_produk';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kode_produk',
        'nama_produk',
    ];
}
