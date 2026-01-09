<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model MsProductImage - Representasi tabel tr_product_image
class MsProductImage extends Model
{
    protected $table = 'tr_product_image';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'url',
        'image_type',
        'main',
        'order',
        'created_by',
    ];

    protected $casts = [
        'main' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeDisplay($query)
    {
        return $query->where('image_type', 'DISPLAY');
    }

    public function scopeLayout($query)
    {
        return $query->where('image_type', 'LAYOUT');
    }

    public function scopeMain($query)
    {
        return $query->where('main', 1);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MsProduct::class, 'product_id', 'product_id');
    }
}
