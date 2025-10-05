<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class OptionalSanctum
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            $plain = substr($auth, 7);
            if ($plain) {
                $pat = PersonalAccessToken::findToken($plain);
                if ($pat && $pat->tokenable) {
                    Auth::setUser($pat->tokenable);
                }
            }
        }
        return $next($request);
    }
}
