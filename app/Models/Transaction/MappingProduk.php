<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class MappingProduk extends Model
{
    protected $table = 'mapping_produk';

    // The primary key is kode_produk (not id)
    protected $primaryKey = 'kode_produk';

    // The primary key is not auto-incrementing
    public $incrementing = false;

    // The primary key type is string
    protected $keyType = 'string';

    // Disable Laravel's default timestamps
    public $timestamps = false;

    protected $fillable = [
        'kode_produk',
        'nama_produk',
    ];
}
