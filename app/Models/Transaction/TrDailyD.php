<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrDailyD extends Model
{
    protected $table = 'tr_daily_d';

    // Disable Laravel's default timestamps since the table uses custom timestamp fields
    public $timestamps = false;

    protected $fillable = [
        'daily_id',
        'user_id',
        'type',
        'kode_produk',
        'nama_produk',
        'quantity',
        'price',
        'note_detail',
        'created_date',
        'created_by',
        'updated_date',
        'updated_by',
        'deleted_date',
        'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
        'deleted_date' => 'datetime',
    ];

    /**
     * Get the header record for this detail
     */
    public function header(): BelongsTo
    {
        return $this->belongsTo(TrDailyH::class, 'daily_id', 'daily_id');
    }

    /**
     * Scope for scan type records
     */
    public function scopeScan($query)
    {
        return $query->where('type', 'scan');
    }

    /**
     * Scope for manual type records
     */
    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    /**
     * Scope for non-deleted records
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_date');
    }
}
