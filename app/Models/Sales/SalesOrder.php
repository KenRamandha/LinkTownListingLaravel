<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
