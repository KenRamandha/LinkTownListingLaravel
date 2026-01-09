<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Support\ProductCache;

// Model Product - Representasi tabel products
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'code',
        'meta_description',
        'meta_title',
        'around',
        'promo',
        'description',
        'benefits',
        'tags',
        'price',
        'cicilan_per_bulan',
        'label',
        'label_color',
        'product_type_id',
        'link',
        'order',
        'status',
        'image_location',
        'youtube',
        'hero_title',
        'hero_list',
        'price_header',
        'hero_subtitle',
        'developer_id',
        'tenant',
        'featured_partner',
        'project_id',
        'user_id',
        'property_status',
        'nowa',
        'namawa',
        'rental_terms',
    ];

    protected $casts = [
        'price'             => 'decimal:0',
        'cicilan_per_bulan' => 'int',
        'around'            => 'array',
        'product_type_id'   => 'int',
        'developer_id'      => 'int',
        'featured_partner'  => 'boolean',
        'project_id'        => 'int',
        'user_id'           => 'int',
        'order'             => 'int',
        'nowa'              => 'int',
        'benefits'          => 'array',
        'tags'              => 'array',
        'hero_list'         => 'array',
        'price_header'      => 'array',
        'tenant'            => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            ProductCache::forgetForProduct((string) $product->getKey());
        });

        static::deleted(function (Product $product) {
            ProductCache::forgetForProduct((string) $product->getKey());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published');
    }

    public function scopePropertyStatus(Builder $query, ?string $propertyStatus): Builder
    {
        if (!is_null($propertyStatus) && $propertyStatus !== '') {
            $query->where('property_status', $propertyStatus);
        }

        return $query;
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(ProductLayout::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function featuredImages(): HasMany
    {
        return $this->images()->where('featured', true);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
