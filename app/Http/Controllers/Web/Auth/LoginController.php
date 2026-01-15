<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Core\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended(route('home'));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required',
        ]);

        $credentials = [
            'password' => $request->password,
        ];

        $normalizedPhone = $this->normalizePhone($request->phone);

        $user = User::with('profile')
            ->whereHas('profile', function ($q) use ($normalizedPhone, $request) {
                $q->where('phone', $normalizedPhone);
                if ($normalizedPhone !== $request->phone) {
                    $q->orWhere('phone', $request->phone);
                }
            })
            ->first();

        if ($user && Hash::check($request->password, $user->password)) {
            if ($user->status !== 'active') {
                throw ValidationException::withMessages([
                    'phone' => ['User account is not active.'],
                ]);
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        throw ValidationException::withMessages([
            'phone' => ['The provided credentials do not match our records.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return trim($phone);
        }

        if (\Illuminate\Support\Str::startsWith($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (\Illuminate\Support\Str::startsWith($digits, '8')) {
            $digits = '62' . $digits;
        } elseif (!\Illuminate\Support\Str::startsWith($digits, '62')) {
            $digits = '62' . ltrim($digits, '0');
        }

        return '+' . $digits;
    }
}
