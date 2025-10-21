<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ProductCache;

class ProductSpecification extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'value',
    ];

    protected $casts = [
        'product_id' => 'int',
        'value'      => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductSpecification $specification) {
            ProductCache::forgetForProduct((string) $specification->product_id);
        });

        static::deleted(function (ProductSpecification $specification) {
            ProductCache::forgetForProduct((string) $specification->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
