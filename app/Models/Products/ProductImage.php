<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ProductCache;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'url',
        'featured',
    ];

    protected $casts = [
        'product_id' => 'int',
        'featured'   => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductImage $image) {
            ProductCache::forgetForProduct((string) $image->product_id);
        });

        static::deleted(function (ProductImage $image) {
            ProductCache::forgetForProduct((string) $image->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
