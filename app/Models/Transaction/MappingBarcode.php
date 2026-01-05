<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class MappingBarcode extends Model
{
    protected $table = 'mapping_barcode';

    // Disable Laravel's default timestamps since the table uses custom timestamp fields
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

    /**
     * Get the default flag value
     */
    protected $attributes = [
        'flag' => '0',
    ];
}
