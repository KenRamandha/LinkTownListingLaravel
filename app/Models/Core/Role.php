<?php

namespace App\Models\Core;

use App\Models\Menus\RoleMenuToken;
use App\Models\Menus\RoleMenuVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'key',
        'name',
        'is_template',
    ];

    protected $casts = [
        'is_template' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('allow')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function menuVisibilities()
    {
        return $this->hasMany(RoleMenuVisibility::class);
    }

    public function menuTokens()
    {
        return $this->hasMany(RoleMenuToken::class);
    }
}
