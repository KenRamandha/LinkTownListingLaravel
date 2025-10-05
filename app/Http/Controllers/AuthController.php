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
            $token = $user->createToken('api')->plainTextToken;

            return $this->ok([
                'token_type'   => 'Bearer',
                'access_token' => $token,
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
