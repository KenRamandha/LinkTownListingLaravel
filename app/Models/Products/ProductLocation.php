<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ProductCache;

// Model ProductLocation - Representasi tabel product_locations
class ProductLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'latitude',
        'longitude',
        'place_id',
        'product_id',
    ];

    protected $casts = [
        'latitude'   => 'string',
        'longitude'  => 'string',
        'place_id'   => 'int',
        'product_id' => 'int',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductLocation $location) {
            ProductCache::forgetForProduct((string) $location->product_id);
        });

        static::deleted(function (ProductLocation $location) {
            ProductCache::forgetForProduct((string) $location->product_id);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
