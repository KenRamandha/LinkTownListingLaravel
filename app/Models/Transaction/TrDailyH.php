<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrDailyH extends Model
{
    protected $table = 'tr_daily_h';

    // Disable Laravel's default timestamps since the table uses custom timestamp fields
    public $timestamps = false;

    protected $fillable = [
        'daily_id',
        'user_id',
        'user_name',
        'transaction_date',
        'transaction_note',
        'description',
        'total_price',
        'status',
        'created_date',
        'created_by',
        'updated_date',
        'updated_by',
        'deleted_date',
        'deleted_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'total_price' => 'float',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
        'deleted_date' => 'datetime',
    ];

    /**
     * Get the route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'daily_id';
    }

    /**
     * Get the detail records for this daily transaction
     */
    public function details(): HasMany
    {
        return $this->hasMany(TrDailyD::class, 'daily_id', 'daily_id');
    }

    /**
     * Scope for non-deleted records
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_date');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for published transactions
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
