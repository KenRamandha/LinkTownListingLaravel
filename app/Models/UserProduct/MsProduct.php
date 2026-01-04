<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MsProduct extends Model
{
    protected $table = 'tr_product';

    // Uses default Laravel timestamps: created_at, updated_at

    protected $fillable = [
        'product_id',
        'title',
        'description',
        'listing_type',
        'province',
        'city',
        'area',
        'address',
        'condition',
        'product_type',
        'facility',
        'specification',
        'label',
        'legal',
        'developer',
        'agreement_date',
        'expired_date',
        'selling_price',
        'rental_price',
        'commission_selling_percentage',
        'commission_rent_percentage',
        'commission_selling_price',
        'commission_rent_price',
        'rental_terms',
        'user_name',
        'user_phone',
        'owner_name',
        'owner_phone',
        'owner_nik',
        'owner_address',
        'owner_email',
        'status',
        'created_by',
        'update_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'rental_price' => 'decimal:2',
        'commission_selling_percentage' => 'integer',
        'commission_rent_percentage' => 'integer',
        'commission_selling_price' => 'decimal:2',
        'commission_rent_price' => 'decimal:2',
        'agreement_date' => 'date',
        'expired_date' => 'date',
    ];

    /**
     * Get the route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'product_id';
    }

    /**
     * Scope for draft products
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'Draft');
    }

    /**
     * Scope for published products
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'Publish');
    }

    /**
     * Get the images for this product
     */
    public function images(): HasMany
    {
        return $this->hasMany(MsProductImage::class, 'product_id', 'product_id');
    }

    /**
     * Get the display images for this product
     */
    public function displayImages(): HasMany
    {
        return $this->images()->where('image_type', 'DISPLAY')->orderBy('order');
    }

    /**
     * Get the layout images for this product
     */
    public function layoutImages(): HasMany
    {
        return $this->images()->where('image_type', 'LAYOUT')->orderBy('order');
    }

    /**
     * Get the main image for this product
     */
    public function mainImage()
    {
        return $this->images()->where('main', 1)->first();
    }

    /**
     * Relationship for main image (Eager Loadable)
     */
    public function mainImageRelation()
    {
        return $this->hasOne(MsProductImage::class, 'product_id', 'product_id')->where('main', 1);
    }

    /**
     * Get the locations for this product
     */
    public function locations(): HasMany
    {
        return $this->hasMany(MsProductLocation::class, 'product_id', 'product_id');
    }

    /**
     * Get decoded specification as array
     */
    public function getSpecificationArrayAttribute(): ?array
    {
        if (empty($this->specification)) {
            return null;
        }
        
        $decoded = json_decode($this->specification, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get decoded facility as array
     */
    public function getFacilityArrayAttribute(): ?array
    {
        if (empty($this->facility)) {
            return null;
        }
        
        $decoded = json_decode($this->facility, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get updated listing type detail
     */
    public function listingTypeDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'listing_type')
                    ->where('detail_type', 'LISTING_TYPE');
    }

    /**
     * Get updated product type detail
     */
    public function productTypeDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'product_type')
                    ->where('detail_type', 'PROPERTY_TYPE');
    }

    /**
     * Get updated condition detail
     */
    public function conditionDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'condition')
                    ->where('detail_type', 'CONDITION');
    }

    /**
     * Get updated label detail
     */
    public function labelDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'label')
                    ->where('detail_type', 'LABEL');
    }

    /**
     * Get the creator of the product (Agent/User)
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'created_by');
    }
}
