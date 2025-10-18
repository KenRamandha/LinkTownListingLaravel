<?php

namespace App\Models\Core;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'department_id',
        'name',
        'email',
        'password',
        'status',
        'is_employee',
        'last_login_at',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'is_employee'   => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function menuTokens()
    {
        return $this->hasMany(\App\Models\Menus\UserMenuToken::class);
    }

    public function menuVisibilityOverrides()
    {
        return $this->hasMany(\App\Models\Menus\MenuUserVisibility::class);
    }

    public function hasPermission(string $key): bool
    {
        $ov = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $this->id)
            ->where('permissions.key', $key)
            ->select('user_permissions.allow')
            ->first();
        if ($ov) {
            return (bool) $ov->allow;
        }

        $roleIds = $this->roles()->pluck('roles.id');
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->where('role_permissions.allow', true)
            ->where('permissions.key', $key)
            ->exists();
    }
}
