<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Core\User;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        try {
            $r->validate(['email' => 'required|email', 'password' => 'required']);

            $user = User::where('email', $r->email)->first();
            if (!$user || !Hash::check($r->password, $user->password)) {
                throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
            }
            if ($user->status !== 'active') {
                return $this->fail('User is not active', 403, 'FORBIDDEN');
            }
            $accessTtlMinutes = (int) env('ACCESS_TOKEN_TTL_MINUTES', 60);
            $refreshTtlDays   = (int) env('REFRESH_TOKEN_TTL_DAYS', 30);

            // Issue short-lived access token
            $newAccess = $user->createToken(
                'access',
                ['*'],
                now()->addMinutes($accessTtlMinutes)
            );

            // Issue long-lived refresh token (ability-gated)
            $newRefresh = $user->createToken(
                'refresh',
                ['token:refresh'],
                now()->addDays($refreshTtlDays)
            );

            return $this->ok([
                'token_type'   => 'Bearer',
                'access_token' => $newAccess->plainTextToken,
                'refresh_token'=> $newRefresh->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_id' => $user->company_id,
                    'is_employee' => $user->is_employee
                ]
            ], 'Login berhasil');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    public function refresh(Request $r)
    {
        try {
            // Auth via access token (Bearer <access_token>)
            $user = $r->user();
            if (!$user) {
                return $this->fail('Unauthenticated', 401, 'UNAUTHENTICATED');
            }

            // Client sends refresh_token in body (not in Authorization)
            $r->validate(['refresh_token' => 'required|string']);
            $providedRefresh = (string) $r->input('refresh_token');

            // Find and verify refresh token
            $refreshPat = PersonalAccessToken::findToken($providedRefresh);
            if (!$refreshPat) {
                return $this->fail('Refresh token tidak valid', 403, 'FORBIDDEN');
            }
            if ($refreshPat->tokenable_id !== $user->getKey() || $refreshPat->tokenable_type !== get_class($user)) {
                return $this->fail('Refresh token tidak sesuai pengguna', 403, 'FORBIDDEN');
            }
            if (($refreshPat->name ?? null) !== 'refresh' || !$refreshPat->can('token:refresh')) {
                return $this->fail('Refresh token tidak memiliki izin yang benar', 403, 'FORBIDDEN');
            }
            if (!is_null($refreshPat->expires_at) && now()->greaterThan($refreshPat->expires_at)) {
                return $this->fail('Refresh token kedaluwarsa', 401, 'UNAUTHENTICATED');
            }

            // Rotate refresh token: delete the used refresh token
            $refreshPat->delete();

            $accessTtlMinutes = (int) env('ACCESS_TOKEN_TTL_MINUTES', 60);
            $refreshTtlDays   = (int) env('REFRESH_TOKEN_TTL_DAYS', 30);

            // Issue new tokens
            $newAccess = $user->createToken(
                'access',
                ['*'],
                now()->addMinutes($accessTtlMinutes)
            );
            $newRefresh = $user->createToken(
                'refresh',
                ['token:refresh'],
                now()->addDays($refreshTtlDays)
            );

            return $this->ok([
                'token_type'    => 'Bearer',
                'access_token'  => $newAccess->plainTextToken,
                'refresh_token' => $newRefresh->plainTextToken,
            ], 'Token refreshed');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal refresh token', 500, 'SERVER_ERROR');
        }
    }

    public function logout(Request $r)
    {
        try {
            $r->user()->currentAccessToken()->delete();
            return $this->ok(null, 'Logout berhasil');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal logout', 500, 'SERVER_ERROR');
        }
    }
}
