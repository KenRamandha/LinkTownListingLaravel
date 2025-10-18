<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'key',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'int',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function features()
    {
        return $this->hasMany(Feature::class);
    }

    public function actions()
    {
        return $this->hasMany(Action::class);
    }
}
