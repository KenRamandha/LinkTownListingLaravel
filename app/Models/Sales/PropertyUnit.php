<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyUnit extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'unit_id');
    }
}
