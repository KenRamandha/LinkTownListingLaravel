<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use App\Support\UserCache;

class RolesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('roles:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('roles')->where('company_id', $u->company_id)->orderBy('name')->get();
            return $this->ok($data, 'Roles');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat roles', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('roles:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate(['key' => 'required|string|max:80', 'name' => 'required|string|max:120', 'is_template' => 'required|boolean']);
            $u = $r->user();
            $id = (string)Str::orderedUuid();
            DB::table('roles')->insert(['id' => $id, 'company_id' => $u->company_id, 'key' => $r->key, 'name' => $r->name, 'is_template' => $r->is_template, 'created_at' => now(), 'updated_at' => now()]);
            return $this->ok(['id' => $id], 'Role dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat role', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('roles:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate(['key' => 'nullable|string|max:80', 'name' => 'nullable|string|max:120', 'is_template' => 'nullable|boolean']);
            $u = $r->user();
            $exists = DB::table('roles')->where('company_id', $u->company_id)->where('id', $id)->exists();
            if (!$exists) return $this->fail('Role tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('key', 'name', 'is_template'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('roles')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Role diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui role', 500, 'SERVER_ERROR');
        }
    }
    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('roles:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $userIds = DB::table('user_roles')->where('role_id', $id)->pluck('user_id')->all();
            $count = DB::table('roles')->where('company_id', $u->company_id)->where('id', $id)->delete();
            if (!$count) return $this->fail('Role tidak ditemukan', 404, 'NOT_FOUND');
            if ($userIds) {
                UserCache::forgetPermissions($userIds);
            }
            return $this->ok(null, 'Role dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus role', 500, 'SERVER_ERROR');
        }
    }
    public function permissions(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('roles:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $role = DB::table('roles')
                ->where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$role) return $this->fail('Role tidak ditemukan', 404, 'NOT_FOUND');

            $perms = DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_id', $id)
                ->select('permissions.id as permission_id', 'permissions.key', 'role_permissions.allow')
                ->get();
            return $this->ok($perms, 'Role permissions');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat role permissions', 500, 'SERVER_ERROR');
        }
    }
    public function setPermissions(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('roles:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $role = DB::table('roles')
                ->where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$role) return $this->fail('Role tidak ditemukan', 404, 'NOT_FOUND');
            $r->validate(['permissions' => 'required|array|min:1', 'permissions.*.permission_id' => 'required|string', 'permissions.*.allow' => 'required|boolean']);
            $userIds = DB::table('user_roles')->where('role_id', $id)->pluck('user_id')->all();
            DB::transaction(function () use ($r, $id) {
                foreach ($r->permissions as $p) {
                    $exists = DB::table('role_permissions')->where('role_id', $id)->where('permission_id', $p['permission_id'])->exists();
                    if ($exists) {
                        DB::table('role_permissions')->where('role_id', $id)->where('permission_id', $p['permission_id'])->update(['allow' => $p['allow'], 'updated_at' => now()]);
                    } else {
                        DB::table('role_permissions')->insert(['role_id' => $id, 'permission_id' => $p['permission_id'], 'allow' => $p['allow'], 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
            if ($userIds) {
                UserCache::forgetPermissions($userIds);
            }
            return $this->ok(null, 'Permissions diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal mengatur permissions', 500, 'SERVER_ERROR');
        }
    }
}
