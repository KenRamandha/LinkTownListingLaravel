<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MeController extends Controller
{
    public function show(Request $r)
    {
        try {
            return $this->ok($r->user()->only(['id', 'email', 'company_id', 'is_employee', 'status']));
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    public function permissions(Request $r)
    {
        try {
            $u = $r->user();
            $rolePerms = DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->whereIn('role_id', $u->roles()->pluck('roles.id'))
                ->where('allow', true)
                ->pluck('permissions.key');

            $userOverrides = DB::table('user_permissions')
                ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                ->where('user_id', $u->id)
                ->select('permissions.key', 'user_permissions.allow')->get();

            $eff = collect($rolePerms)->flip()->map(fn() => true)->toArray();
            foreach ($userOverrides as $ov) {
                $eff[$ov->key] = (bool)$ov->allow;
            }

            return $this->ok(['permissions' => array_keys(array_filter($eff))]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat permissions', 500, 'SERVER_ERROR');
        }
    }

    public function profile(Request $r)
    {
        try {
            $u = $r->user();
            $data = DB::table('user_profiles')->where('user_id', $u->id)->first();
            return $this->ok($data ?: (object)[], 'My profile');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    public function updateProfile(Request $r)
    {
        try {
            $r->validate([
                'phone' => 'nullable|string|max:50',
                'avatar_url' => 'nullable|string',
                'position' => 'nullable|string|max:100',
            ]);
            $u = $r->user();
            $exists = DB::table('user_profiles')->where('user_id', $u->id)->exists();
            $payload = array_filter($r->only('phone', 'avatar_url', 'position'), fn($v) => !is_null($v));
            if ($exists) {
                $payload['updated_at'] = now();
                DB::table('user_profiles')->where('user_id', $u->id)->update($payload);
            } else {
                $payload = array_merge($payload, [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'user_id' => $u->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('user_profiles')->insert($payload);
            }
            return $this->ok(null, 'Profil diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui profil', 500, 'SERVER_ERROR');
        }
    }
}
