<?php

namespace App\Models\Menus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model Menu - Representasi tabel menus
class Menu extends Model
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Core\Company::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function roleVisibilities()
    {
        return $this->hasMany(RoleMenuVisibility::class);
    }

    public function userTokens()
    {
        return $this->hasMany(UserMenuToken::class);
    }

    public function roleTokens()
    {
        return $this->hasMany(RoleMenuToken::class);
    }
}
