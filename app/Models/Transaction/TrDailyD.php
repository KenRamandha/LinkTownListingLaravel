<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model TrDailyD - Representasi tabel tr_daily_d
class TrDailyD extends Model
{
    protected $table = 'tr_daily_d';

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
        'barcode',
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
        'barcode' => 'json',
        'created_date' => 'datetime',
        'updated_date' => 'datetime',
        'deleted_date' => 'datetime',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(TrDailyH::class, 'daily_id', 'daily_id');
    }

    public function scopeScan($query)
    {
        return $query->where('type', 'scan');
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_date');
    }
}
