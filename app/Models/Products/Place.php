<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Model Place - Representasi tabel places
class Place extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'featured',
        'image',
        'icon',
        'hero',
        'price',
        'price_text',
        'order',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'city_id'   => 'int',
        'id'        => 'int',
        'featured'  => 'boolean',
        'price'     => 'decimal:0',
        'order'     => 'int',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }
}
