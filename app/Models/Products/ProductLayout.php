<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ProductCache;

class ProductLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image',
        'description',
    ];

    protected $casts = [
        'product_id' => 'int',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductLayout $layout) {
            ProductCache::forgetForProduct((string) $layout->product_id);
        });

        static::deleted(function (ProductLayout $layout) {
            ProductCache::forgetForProduct((string) $layout->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
