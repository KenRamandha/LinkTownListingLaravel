<?php

namespace App\Models\Core;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Support\UserCache;

// Model User - Representasi tabel users
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
        'akses_web',
        'last_login_at',
        'lamudi_api',
        'ms_api',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_employee' => 'boolean',
        'last_login_at' => 'datetime',
        'ms_api' => 'integer',
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

    /**
     * Get the Lamudi/Proppit API configuration for this user.
     */
    public function msApi()
    {
        return $this->belongsTo(\App\Models\UserProduct\MsApi::class, 'ms_api', 'id_api');
    }

    public function menuTokens()
    {
        return $this->hasMany(\App\Models\Menus\UserMenuToken::class);
    }

    public function menuVisibilityOverrides()
    {
        return $this->hasMany(\App\Models\Menus\MenuUserVisibility::class);
    }

    protected ?array $effectivePermissionCache = null;

    /**
     * Determine whether the user has the given permission key.
     */
    public function hasPermission(string $key): bool
    {
        if (!$this->getKey()) {
            return false;
        }

        $map = $this->resolveEffectivePermissionMap();

        return (bool) ($map[$key] ?? false);
    }

    /**
     * Get the effective permissions map or list of keys for the user.
     *
     * @param  bool  $onlyAllowedKeys  Return only allowed permission keys when true.
     * @param  bool  $refresh          Force refresh of the cached map.
     */
    public function effectivePermissions(bool $onlyAllowedKeys = true, bool $refresh = false): array
    {
        if (!$this->getKey()) {
            return [];
        }

        $map = $this->resolveEffectivePermissionMap($refresh);

        if ($onlyAllowedKeys) {
            return array_keys(array_filter($map));
        }

        return $map;
    }

    /**
     * Clear the cached permission map for the user.
     */
    public function refreshEffectivePermissionCache(): void
    {
        $this->effectivePermissionCache = null;
    }

    /**
     * Resolve the effective permission map, combining role permissions and overrides.
     */
    protected function resolveEffectivePermissionMap(bool $refresh = false): array
    {
        if (!$refresh) {
            if (!is_null($this->effectivePermissionCache)) {
                return $this->effectivePermissionCache;
            }
        }

        $map = [];

        $rolePermissions = DB::table('role_permissions')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $this->id)
            ->where('role_permissions.allow', true)
            ->pluck('permissions.key');

        foreach ($rolePermissions as $key) {
            $map[$key] = true;
        }

        $overrides = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $this->id)
            ->get(['permissions.key', 'user_permissions.allow']);

        foreach ($overrides as $row) {
            $map[$row->key] = (bool) $row->allow;
        }

        return $this->effectivePermissionCache = $map;
    }
}
