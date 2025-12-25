<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;

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

    /**
     * Valid detail types
     */
    public const TYPES = [
        'SPEC',
        'FACILITY',
        'CONDITION',
        'PROPERTY_TYPE',
        'LEGAL',
        'LABEL',
        'LISTING_TYPE',
    ];

    /**
     * Scope to filter by detail type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('detail_type', strtoupper($type));
    }

    /**
     * Scope for specifications
     */
    public function scopeSpecs($query)
    {
        return $query->where('detail_type', 'SPEC');
    }

    /**
     * Scope for facilities
     */
    public function scopeFacilities($query)
    {
        return $query->where('detail_type', 'FACILITY');
    }

    /**
     * Scope for conditions
     */
    public function scopeConditions($query)
    {
        return $query->where('detail_type', 'CONDITION');
    }

    /**
     * Scope for property types
     */
    public function scopePropertyTypes($query)
    {
        return $query->where('detail_type', 'PROPERTY_TYPE');
    }

    /**
     * Scope for legal types
     */
    public function scopeLegals($query)
    {
        return $query->where('detail_type', 'LEGAL');
    }

    /**
     * Scope for labels
     */
    public function scopeLabels($query)
    {
        return $query->where('detail_type', 'LABEL');
    }

    /**
     * Scope for listing types
     */
    public function scopeListingTypes($query)
    {
        return $query->where('detail_type', 'LISTING_TYPE');
    }
}
