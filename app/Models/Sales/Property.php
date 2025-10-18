<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function units()
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }
}
