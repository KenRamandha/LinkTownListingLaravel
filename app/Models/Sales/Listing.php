<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }
}
