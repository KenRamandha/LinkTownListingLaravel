<?php


namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MeController extends Controller
{
    // GET /api/me - Ambil data user yang sedang login
    public function show(Request $r)
    {
        try {
            return $this->ok($r->user()->only(['id', 'email', 'company_id', 'is_employee', 'status']));
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    // GET /api/me/permissions - Ambil daftar permissions user
    public function permissions(Request $r)
    {
        try {
            $u = $r->user();
            $permissions = $u->effectivePermissions();

            return $this->ok(['permissions' => $permissions]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat permissions', 500, 'SERVER_ERROR');
        }
    }

    // GET /api/me/profile - Ambil profil lengkap user
    public function profile(Request $r)
    {
        try {
            $u = $r->user();
            $data = DB::table('user_profiles')->where('user_id', $u->id)->first();
            return $this->ok((object) $data, 'My profile');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    // PUT /api/me/profile - Update profil user (phone, avatar, position)
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
