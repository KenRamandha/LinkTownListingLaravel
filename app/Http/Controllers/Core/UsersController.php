<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use App\Models\Core\User;

class UsersController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->query('company_id', $r->user()->company_id);
            $q = DB::table('users')
                ->where('company_id', $companyId)
                ->select('id', 'company_id', 'department_id', 'name', 'email', 'status', 'is_employee', 'last_login_at');
            if ($s = $r->query('q')) {
                $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%$s%")
                        ->orWhere('email', 'like', "%$s%");
                });
            }
            $data = $q->orderBy('name')->limit((int)$r->query('limit', 50))->get();
            return $this->ok($data, 'Users');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat users', 500, 'SERVER_ERROR');
        }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('users:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'company_id' => 'nullable|string',
                'department_id' => 'nullable|string',
                'name' => 'required|string|max:120',
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'is_employee' => 'required|boolean',
                'status' => 'required|in:active,suspended,inactive'
            ]);
            $companyId = $r->input('company_id', $r->user()->company_id);
            if ($companyId !== $r->user()->company_id) {
                return $this->fail('Tidak dapat membuat user untuk perusahaan lain', 403, 'FORBIDDEN');
            }
            $id = (string)Str::orderedUuid();
            DB::table('users')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'department_id' => $r->department_id,
                'name' => $r->name,
                'email' => $r->email,
                'password' => Hash::make($r->password),
                'is_employee' => $r->is_employee,
                'status' => $r->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'User dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat user', 500, 'SERVER_ERROR');
        }
    }

    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('users')
                ->select('id', 'company_id', 'department_id', 'name', 'email', 'status', 'is_employee', 'last_login_at', 'created_at', 'updated_at')
                ->where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$data) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'User');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat user', 500, 'SERVER_ERROR');
        }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'company_id' => 'nullable|string',
                'department_id' => 'nullable|string',
                'name' => 'nullable|string|max:120',
                'email' => 'nullable|email',
                'password' => 'nullable|string|min:6',
                'is_employee' => 'nullable|boolean',
                'status' => 'nullable|in:active,suspended,inactive'
            ]);
            $userRow = DB::table('users')
                ->select('id', 'company_id')
                ->where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('company_id', 'department_id', 'name', 'email', 'is_employee', 'status'), fn($v) => !is_null($v));
            if (isset($upd['company_id']) && $upd['company_id'] !== $r->user()->company_id) {
                return $this->fail('Tidak dapat memindahkan user ke perusahaan lain', 403, 'FORBIDDEN');
            }
            $upd['company_id'] = $r->user()->company_id;
            if ($r->filled('password')) $upd['password'] = Hash::make($r->password);
            $upd['updated_at'] = now();
            DB::table('users')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'User diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui user', 500, 'SERVER_ERROR');
        }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $count = DB::table('users')->where('id', $id)->where('company_id', $r->user()->company_id)->delete();
            if (!$count) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'User dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus user', 500, 'SERVER_ERROR');
        }
    }

    public function roles(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userRow = DB::table('users')
                ->select('id', 'company_id')
                ->where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $roles = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $id)
                ->where('roles.company_id', $r->user()->company_id)
                ->select('roles.id', 'roles.key', 'roles.name')
                ->get();
            return $this->ok($roles, 'User roles');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat user roles', 500, 'SERVER_ERROR');
        }
    }

    public function setRoles(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userRow = DB::table('users')->where('id', $id)->where('company_id', $r->user()->company_id)->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $r->validate(['role_ids' => 'required|array|min:1', 'role_ids.*' => 'string']);
            $validRoleIds = DB::table('roles')->whereIn('id', $r->role_ids)->where('company_id', $r->user()->company_id)->pluck('id')->all();
            if (count($validRoleIds) !== count($r->role_ids)) return $this->fail('Beberapa role tidak valid untuk perusahaan ini', 422, 'INVALID_ROLE');
            DB::transaction(function () use ($id, $validRoleIds) {
                DB::table('user_roles')->where('user_id', $id)->delete();
                $rows = [];
                foreach ($validRoleIds as $rid) {
                    $rows[] = ['user_id' => $id, 'role_id' => $rid, 'created_at' => now(), 'updated_at' => now()];
                }
                if ($rows) DB::table('user_roles')->insert($rows);
            });
            return $this->ok(null, 'User roles diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui user roles', 500, 'SERVER_ERROR');
        }
    }

    public function permissions(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userModel = User::where('id', $id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$userModel) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');

            $permissions = $userModel->effectivePermissions();

            return $this->ok(['permissions' => $permissions], 'Effective permissions');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat permissions', 500, 'SERVER_ERROR');
        }
    }

    public function setPermissions(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userRow = DB::table('users')->where('id', $id)->where('company_id', $r->user()->company_id)->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $r->validate(['overrides' => 'required|array|min:1', 'overrides.*.permission_id' => 'required|string', 'overrides.*.allow' => 'required|boolean']);
            DB::transaction(function () use ($r, $id) {
                foreach ($r->overrides as $ov) {
                    $exists = DB::table('user_permissions')->where('user_id', $id)->where('permission_id', $ov['permission_id'])->exists();
                    if ($exists) {
                        DB::table('user_permissions')->where('user_id', $id)->where('permission_id', $ov['permission_id'])->update(['allow' => $ov['allow'], 'updated_at' => now()]);
                    } else {
                        DB::table('user_permissions')->insert(['user_id' => $id, 'permission_id' => $ov['permission_id'], 'allow' => $ov['allow'], 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
            return $this->ok(null, 'User permission overrides diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal mengatur user permissions', 500, 'SERVER_ERROR');
        }
    }

    public function profile(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userRow = DB::table('users')->where('id', $id)->where('company_id', $r->user()->company_id)->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $data = DB::table('user_profiles')->where('user_id', $id)->first();
            return $this->ok((object) $data, 'User profile');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat user profile', 500, 'SERVER_ERROR');
        }
    }

    public function updateProfile(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $userRow = DB::table('users')->where('id', $id)->where('company_id', $r->user()->company_id)->first();
            if (!$userRow) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $r->validate([
                'employee_code' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:50',
                'avatar_url' => 'nullable|string',
                'department_id' => 'nullable|string',
                'position' => 'nullable|string|max:100',
                'join_date' => 'nullable|date',
                'resign_date' => 'nullable|date'
            ]);
            $exists = DB::table('user_profiles')->where('user_id', $id)->exists();
            $payload = array_merge(['user_id' => $id], $r->only('employee_code', 'phone', 'avatar_url', 'department_id', 'position', 'join_date', 'resign_date'));
            if ($exists) {
                $payload['updated_at'] = now();
                DB::table('user_profiles')->where('user_id', $id)->update($payload);
            } else {
                $payload['id'] = (string)Str::orderedUuid();
                $payload['created_at'] = now();
                $payload['updated_at'] = now();
                DB::table('user_profiles')->insert($payload);
            }
            return $this->ok(null, 'User profile diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui user profile', 500, 'SERVER_ERROR');
        }
    }
}
