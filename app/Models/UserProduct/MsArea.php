<?php

namespace App\Models\UserProduct;

use App\Models\Products\Place;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Model MsArea - Representasi tabel ms_areas
class MsArea extends Model
{
    use SoftDeletes;

    protected $table = 'ms_areas';

    protected $fillable = [
        'place_id',
        'name',
        'order',
        'is_active',
    ];

    protected $casts = [
        'place_id' => 'integer',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
