<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model MsProductLocation - Representasi tabel tr_product_location
class MsProductLocation extends Model
{
    protected $table = 'tr_product_location';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'update_at';

    protected $fillable = [
        'product_id',
        'latitude',
        'longitude',
        'created_by',
        'update_by',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MsProduct::class, 'product_id', 'product_id');
    }
}

