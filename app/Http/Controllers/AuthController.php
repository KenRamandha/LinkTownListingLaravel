<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Core\User;
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
            $user = $r->user();
            if (!$user) {
                return $this->fail('Unauthenticated', 401, 'UNAUTHENTICATED');
            }

            // Ensure current token is a refresh token and still valid
            if (!$user->tokenCan('token:refresh')) {
                return $this->fail('Invalid token ability', 403, 'FORBIDDEN');
            }

            $currentToken = $user->currentAccessToken();
            if (!$currentToken) {
                return $this->fail('Token not found', 401, 'UNAUTHENTICATED');
            }

            // Rotate refresh token: delete the used refresh token
            $currentToken->delete();

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
