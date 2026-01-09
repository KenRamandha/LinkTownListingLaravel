<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Model TrDailyH - Representasi tabel tr_daily_h
class TrDailyH extends Model
{
    protected $table = 'tr_daily_h';

    public $timestamps = false;

    protected $fillable = [
        'daily_id',
        'user_id',
        'user_name',
        'transaction_date',
        'transaction_note',
        'description',
        'total_price',
        'url',
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

    public function getRouteKeyName(): string
    {
        return 'daily_id';
    }

    public function details(): HasMany
    {
        return $this->hasMany(TrDailyD::class, 'daily_id', 'daily_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_date');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
