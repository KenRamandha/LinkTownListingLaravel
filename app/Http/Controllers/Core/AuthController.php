<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Core\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const ACCESS_TOKEN_NAME  = 'access';
    private const REFRESH_TOKEN_NAME = 'refresh';
    private const PLAIN_TOKEN_PREFIX = 'plain_token:';
    private const DEFAULT_TOKEN_TTL_DAYS = 7;

    // POST /api/auth/login - Login user dengan phone dan password, return access & refresh token
    public function login(Request $r)
    {
        try {
            $r->validate(['phone' => 'required|string', 'password' => 'required']);

            $normalizedPhone = $this->normalizePhone($r->phone);

            $user = User::with('profile')
                ->whereHas('profile', function ($q) use ($normalizedPhone, $r) {
                    $q->where('phone', $normalizedPhone);

                    if ($normalizedPhone !== $r->phone) {
                        $q->orWhere('phone', $r->phone);
                    }
                })
                ->first();

            if (!$user || !Hash::check($r->password, $user->password)) {
                throw ValidationException::withMessages(['phone' => ['Invalid credentials']]);
            }
            if ($user->status !== 'active') {
                return $this->fail('User is not active', 403, 'FORBIDDEN');
            }
            $this->purgeExpiredTokens($user);

            $accessToken = $this->resolveToken(
                $user,
                self::ACCESS_TOKEN_NAME,
                ['*'],
                $this->tokenExpiry()
            );

            $refreshToken = $this->resolveToken(
                $user,
                self::REFRESH_TOKEN_NAME,
                ['token:refresh'],
                $this->tokenExpiry()
            );

            return $this->ok([
                'token_type'   => 'Bearer',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => optional($user->profile)->phone,
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

    // POST /api/auth/refresh - Refresh access token menggunakan refresh token
    public function refresh(Request $r)
    {
        try {
            $user = $r->user();
            if (!$user) {
                return $this->fail('Unauthenticated', 401, 'UNAUTHENTICATED');
            }

            $r->validate(['refresh_token' => 'required|string']);
            $providedRefresh = (string) $r->input('refresh_token');

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
                $refreshPat->delete();
                return $this->fail('Refresh token kedaluwarsa', 401, 'UNAUTHENTICATED');
            }

            $this->purgeExpiredTokens($user);

            $refreshToken = $this->resolveToken(
                $user,
                self::REFRESH_TOKEN_NAME,
                ['token:refresh'],
                $this->tokenExpiry(),
                $refreshPat,
                $providedRefresh
            );

            $accessToken = $this->resolveToken(
                $user,
                self::ACCESS_TOKEN_NAME,
                ['*'],
                $this->tokenExpiry()
            );

            return $this->ok([
                'token_type'    => 'Bearer',
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
            ], 'Token refreshed');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal refresh token', 500, 'SERVER_ERROR');
        }
    }

    // POST /api/auth/logout - Logout user dan hapus token saat ini
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

    private function tokenExpiry(): Carbon
    {
        $days = (int) env('ACCESS_TOKEN_TTL_DAYS', self::DEFAULT_TOKEN_TTL_DAYS);
        if ($days <= 0) {
            $days = self::DEFAULT_TOKEN_TTL_DAYS;
        }

        return now()->addDays($days);
    }

    private function purgeExpiredTokens(?User $user = null): void
    {
        $query = PersonalAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        if ($user) {
            $query->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey());
        }

        $query->delete();
    }

    private function resolveToken(
        User $user,
        string $name,
        array $abilities,
        Carbon $expiresAt,
        ?PersonalAccessToken $preferredToken = null,
        ?string $plainFromRequest = null
    ): string {
        $now = now();

        $token = $preferredToken ?? $user->tokens()
            ->where('name', $name)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($token) {
            $plainToken = $plainFromRequest ?: $this->extractPlainToken($token);
            if ($plainToken) {
                $this->syncTokenState($token, $abilities, $plainToken, $expiresAt);
                return $plainToken;
            }

            $token->delete();
        }

        return $this->issueFreshToken($user, $name, $abilities, $expiresAt);
    }

    private function extractPlainToken(PersonalAccessToken $token): ?string
    {
        foreach ((array) $token->abilities as $ability) {
            if (is_string($ability) && Str::startsWith($ability, self::PLAIN_TOKEN_PREFIX)) {
                return Str::after($ability, self::PLAIN_TOKEN_PREFIX);
            }
        }

        return null;
    }

    private function syncTokenState(
        PersonalAccessToken $token,
        array $requiredAbilities,
        string $plainTextToken,
        Carbon $expiresAt
    ): void {
        $abilities = array_values(array_filter(
            (array) $token->abilities,
            fn($ability) => !is_string($ability) || !Str::startsWith($ability, self::PLAIN_TOKEN_PREFIX)
        ));

        foreach ($requiredAbilities as $ability) {
            if (!in_array($ability, $abilities, true)) {
                $abilities[] = $ability;
            }
        }

        $abilities[] = self::PLAIN_TOKEN_PREFIX . $plainTextToken;

        $attributes = ['abilities' => $abilities];

        if (is_null($token->expires_at) || $token->expires_at->lt($expiresAt)) {
            $attributes['expires_at'] = $expiresAt;
        }

        $token->forceFill($attributes)->save();
    }

    private function issueFreshToken(User $user, string $name, array $abilities, Carbon $expiresAt): string
    {
        $newToken = $user->createToken($name, $abilities, $expiresAt);
        $plainText = $newToken->plainTextToken;

        $this->syncTokenState($newToken->accessToken, $abilities, $plainText, $expiresAt);

        return $plainText;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return trim($phone);
        }

        if (Str::startsWith($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (Str::startsWith($digits, '8')) {
            $digits = '62' . $digits;
        } elseif (!Str::startsWith($digits, '62')) {
            $digits = '62' . ltrim($digits, '0');
        }

        return '+' . $digits;
    }
}
