<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'state',
        'country',
        'image',
    ];

    protected $casts = [
        'id' => 'int',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
