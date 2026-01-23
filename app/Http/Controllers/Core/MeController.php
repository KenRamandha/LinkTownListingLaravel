<?php


namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\UserProfile;
use Illuminate\Support\Facades\Storage;
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
            $profile = DB::table('user_profiles')->where('user_id', $u->id)->first();

            if (!$profile) {
                return $this->ok([
                    'id' => null,
                    'user_id' => $u->id,
                    'phone' => null,
                    'avatar_url' => null,
                    'position' => null,
                    'name' => $u->name,
                    'email' => $u->email,
                ], 'My profile');
            }

            $data = (array) $profile;
            $data['email'] = $u->email;

            return $this->ok($data, 'My profile');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    // PUT /api/me/profile - Update profil user (phone, position)
    public function updateProfile(Request $r)
    {
        try {
            $r->validate([
                'phone' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:100',
            ]);

            $u = $r->user();
            $exists = DB::table('user_profiles')->where('user_id', $u->id)->exists();
            $payload = array_filter($r->only('phone', 'position'), fn($v) => !is_null($v));

            if ($exists) {
                $payload['updated_at'] = now();
                DB::table('user_profiles')->where('user_id', $u->id)->update($payload);
            } else {
                $payload = array_merge($payload, [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'user_id' => $u->id,
                    'avatar_url' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('user_profiles')->insert($payload);
            }

            // Get updated profile
            $profile = DB::table('user_profiles')->where('user_id', $u->id)->first();
            $data = (array) $profile;
            $data['email'] = $u->email;

            return $this->ok($data, 'Profil berhasil diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail(
                'Validasi gagal',
                422,
                'VALIDATION_ERROR',
                $e->errors()
            );
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui profil', 500, 'SERVER_ERROR');
        }
    }

    // Helper: Delete image file from storage
    private function deleteImageFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        // Extract path from URL (handle both full URL and relative path)
        $path = $url;

        // Remove full URL base if present
        if (str_starts_with($path, 'http')) {
            $path = parse_url($path, PHP_URL_PATH);
        }

        // Remove /storage/ prefix if present
        $path = str_replace('/storage/', '', $path);

        // Delete file if exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // Helper: Convert storage path to public URL
    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');
        return asset('storage/' . $path);
    }

    // POST /api/me/profile/photo - Update foto profil user
    public function updatePhoto(Request $r)
    {
        try {
            // Validasi HANYA field photo
            $r->validate([
                'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            ]);

            $user = $r->user();

            // Get or create profile using Eloquent model
            $profile = UserProfile::firstOrNew(
                ['user_id' => $user->id],
                [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'name' => $user->name,
                    'phone' => null,
                    'position' => null,
                    'avatar_url' => null,
                ]
            );

            // Delete old photo if exists
            if ($profile->avatar_url) {
                $this->deleteImageFile($profile->avatar_url);
            }

            // Create user directory if not exists
            $directory = 'user/' . $user->id;
            Storage::disk('public')->makeDirectory($directory);

            // Upload new photo with unique filename
            $file = $r->file('photo');
            $filename = 'profile_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $filename, 'public');

            // HANYA update avatar_url dan updated_at
            // TIDAK mengubah field lain (phone, position, dll)
            $profile->avatar_url = $this->publicUrl($path);

            // Set timestamps only if it's a new record
            if (!$profile->exists) {
                $profile->created_at = now();
            }
            $profile->updated_at = now();
            $profile->save();

            // Get fresh profile data
            $updatedProfile = $profile->fresh();

            $data = $updatedProfile->toArray();
            $data['email'] = $user->email;

            return $this->ok($data, 'Foto profil berhasil diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail(
                'Validasi gagal',
                422,
                'VALIDATION_ERROR',
                $e->errors()
            );
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui foto profil', 500, 'SERVER_ERROR');
        }
    }
}
