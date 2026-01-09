<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;

// Model MsProductDetail - Representasi tabel tr_product_detail
class MsProductDetail extends Model
{
    protected $table = 'tr_product_detail';

    protected $fillable = [
        'detail_id',
        'description',
        'icon',
        'detail_type',
        'created_by',
        'updated_by',
    ];

    public const TYPES = [
        'SPEC',
        'FACILITY',
        'CONDITION',
        'PROPERTY_TYPE',
        'LEGAL',
        'LABEL',
        'LISTING_TYPE',
    ];

    public function scopeOfType($query, string $type)
    {
        return $query->where('detail_type', strtoupper($type));
    }

    public function scopeSpecs($query)
    {
        return $query->where('detail_type', 'SPEC');
    }

    public function scopeFacilities($query)
    {
        return $query->where('detail_type', 'FACILITY');
    }

    public function scopeConditions($query)
    {
        return $query->where('detail_type', 'CONDITION');
    }

    public function scopePropertyTypes($query)
    {
        return $query->where('detail_type', 'PROPERTY_TYPE');
    }

    public function scopeLegals($query)
    {
        return $query->where('detail_type', 'LEGAL');
    }

    public function scopeLabels($query)
    {
        return $query->where('detail_type', 'LABEL');
    }

    public function scopeListingTypes($query)
    {
        return $query->where('detail_type', 'LISTING_TYPE');
    }
}
