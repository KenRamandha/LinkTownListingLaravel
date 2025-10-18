<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }
}
