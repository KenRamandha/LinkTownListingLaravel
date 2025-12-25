<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MsProductImage extends Model
{
    protected $table = 'tr_product_image';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;  // Table doesn't have updated_at column

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

    /**
     * Scope for display images
     */
    public function scopeDisplay($query)
    {
        return $query->where('image_type', 'DISPLAY');
    }

    /**
     * Scope for layout images
     */
    public function scopeLayout($query)
    {
        return $query->where('image_type', 'LAYOUT');
    }

    /**
     * Scope for main image
     */
    public function scopeMain($query)
    {
        return $query->where('main', 1);
    }

    /**
     * Get the product that owns the image
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MsProduct::class, 'product_id', 'product_id');
    }
}
