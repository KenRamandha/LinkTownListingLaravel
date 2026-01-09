<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Model MsProduct - Representasi tabel tr_product
class MsProduct extends Model
{
    protected $table = 'tr_product';

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

    public function getRouteKeyName(): string
    {
        return 'product_id';
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'Draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'Publish');
    }

    public function images(): HasMany
    {
        return $this->hasMany(MsProductImage::class, 'product_id', 'product_id');
    }

    public function displayImages(): HasMany
    {
        return $this->images()->where('image_type', 'DISPLAY')->orderBy('order');
    }

    public function layoutImages(): HasMany
    {
        return $this->images()->where('image_type', 'LAYOUT')->orderBy('order');
    }

    public function mainImage()
    {
        return $this->images()->where('main', 1)->first();
    }

    public function mainImageRelation()
    {
        return $this->hasOne(MsProductImage::class, 'product_id', 'product_id')->where('main', 1);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MsProductLocation::class, 'product_id', 'product_id');
    }

    public function getSpecificationArrayAttribute(): ?array
    {
        if (empty($this->specification)) {
            return null;
        }
        
        $decoded = json_decode($this->specification, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getFacilityArrayAttribute(): ?array
    {
        if (empty($this->facility)) {
            return null;
        }
        
        $decoded = json_decode($this->facility, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function listingTypeDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'listing_type')
                    ->where('detail_type', 'LISTING_TYPE');
    }

    public function productTypeDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'product_type')
                    ->where('detail_type', 'PROPERTY_TYPE');
    }

    public function conditionDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'condition')
                    ->where('detail_type', 'CONDITION');
    }

    public function labelDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'label')
                    ->where('detail_type', 'LABEL');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'created_by');
    }
}
