<?php

namespace App\Models\UserProduct;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Model MsProduct - Representasi tabel tr_product
class MsProduct extends Model
{
    protected $table = 'tr_product';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'product_id';

    /**
     * The "type" of the auto-incrementing ID.
     */
    public $incrementing = false;

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
        // Lamudi integration fields
        'lamudi_reference_id',
        'lamudi_synced_at',
        'lamudi_sync_status',
        'lamudi_error_message',
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
        'lamudi_synced_at' => 'datetime',
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

    public function brochureImage(): HasOne
    {
        return $this->hasOne(MsProductImage::class, 'product_id', 'product_id')->where('image_type', 'BROCHURE');
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

    /**
     * Get label as array with backward compatibility for existing string data.
     * - New data: stored as JSON array -> returns as array
     * - Old data: stored as string "LABEL-TYPE-1" -> returns as ["LABEL-TYPE-1"]
     */
    public function getLabelArrayAttribute(): ?array
    {
        if (empty($this->label)) {
            return null;
        }
        
        // Try to decode as JSON first (new format)
        $decoded = json_decode($this->label, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        
        // Fallback: single string (old format) -> wrap in array
        return [$this->label];
    }

    // Relasi label tunggal (untuk backward compatibility)
    public function labelDetail(): HasOne
    {
        return $this->hasOne(MsProductDetail::class, 'detail_id', 'label')
                    ->where('detail_type', 'LABEL');
    }

    /**
     * Get all label details for multiple labels.
     */
    public function labelDetails()
    {
        $labels = $this->label_array ?? [];
        return MsProductDetail::whereIn('detail_id', $labels)
            ->where('detail_type', 'LABEL')
            ->get();
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'created_by');
    }

    // Lamudi integration helper methods

    /**
     * Check if product is synced with Lamudi
     */
    public function isSyncedWithLamudi(): bool
    {
        return !empty($this->lamudi_reference_id) && $this->lamudi_sync_status === 'synced';
    }

    /**
     * Check if Lamudi sync is pending
     */
    public function isLamudiSyncPending(): bool
    {
        return $this->lamudi_sync_status === 'pending';
    }

    /**
     * Check if Lamudi sync failed
     */
    public function isLamudiSyncFailed(): bool
    {
        return $this->lamudi_sync_status === 'failed';
    }

    /**
     * Mark product as synced with Lamudi
     */
    public function markAsLamudiSynced(string $referenceId): void
    {
        $this->update([
            'lamudi_reference_id' => $referenceId,
            'lamudi_sync_status' => 'synced',
            'lamudi_synced_at' => now(),
            'lamudi_error_message' => null,
        ]);
    }

    /**
     * Mark product as Lamudi sync failed
     * Updates all lamudi columns with failed status
     */
    public function markAsLamudiFailed(string $errorMessage): void
    {
        $this->update([
            'lamudi_reference_id' => null,
            'lamudi_sync_status' => 'failed',
            'lamudi_synced_at' => now(),
            'lamudi_error_message' => $errorMessage,
        ]);
    }

    /**
     * Reset Lamudi sync status to pending
     */
    public function resetLamudiSync(): void
    {
        $this->update([
            'lamudi_reference_id' => null,
            'lamudi_sync_status' => 'pending',
            'lamudi_synced_at' => null,
            'lamudi_error_message' => null,
        ]);
    }

    /**
     * Check if product should retry Lamudi sync
     * Returns true if sync failed and product is published
     */
    public function shouldRetryLamudiSync(): bool
    {
        return $this->lamudi_sync_status === 'failed'
            && $this->status === 'Publish';
    }

    /**
     * Check if Lamudi sync is stale (needs refresh)
     * Returns true if last sync was more than 24 hours ago
     */
    public function isLamudiSyncStale(): bool
    {
        if (!$this->lamudi_synced_at || $this->lamudi_sync_status !== 'synced') {
            return false;
        }

        return $this->lamudi_synced_at->lt(now()->subHours(24));
    }

    /**
     * Scope: Get products that need Lamudi sync retry
     */
    public function scopeNeedsLamudiRetry($query)
    {
        return $query->where('status', 'Publish')
            ->where('lamudi_sync_status', 'failed');
    }

    /**
     * Scope: Get products that are synced with Lamudi
     */
    public function scopeLamudiSynced($query)
    {
        return $query->where('lamudi_sync_status', 'synced');
    }

    /**
     * Scope: Get products with failed Lamudi sync
     */
    public function scopeLamudiFailed($query)
    {
        return $query->where('lamudi_sync_status', 'failed');
    }
}
