<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Company;
use App\Models\Core\Department;
use App\Models\Core\Role;
use App\Models\Core\User;
use App\Models\Core\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RegisterController extends Controller
{
    /**
     * GET /api/auth/companies
     * Ambil daftar perusahaan untuk dropdown
     */
    public function getCompanies()
    {
        try {
            $companies = Company::select('id', 'name')->orderBy('name')->get();
            return $this->ok($companies, 'Daftar perusahaan berhasil dimuat');
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'Gagal memuat daftar perusahaan',
            ], 500);
        }
    }

    /**
     * GET /api/auth/departments
     * Ambil daftar departemen untuk dropdown
     */
    public function getDepartments(Request $r)
    {
        try {
            $query = Department::select('id', 'company_id', 'name');

            if ($companyId = $r->query('company_id')) {
                $query->where('company_id', $companyId);
            }

            $departments = $query->orderBy('name')->get();
            return $this->ok($departments, 'Daftar departemen berhasil dimuat');
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'Gagal memuat daftar departemen',
            ], 500);
        }
    }

    /**
     * GET /api/auth/positions
     * Ambil daftar posisi unik dari user_profiles
     */
    public function getPositions()
    {
        try {
            $positions = UserProfile::whereNotNull('position')
                ->where('position', '!=', '')
                ->select('position')
                ->distinct()
                ->orderBy('position')
                ->pluck('position');

            return $this->ok($positions, 'Daftar posisi berhasil dimuat');
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'Gagal memuat daftar posisi',
            ], 500);
        }
    }

    /**
     * GET /api/auth/roles
     * Ambil daftar roles berdasarkan company_id
     */
    public function getRoles(Request $r)
    {
        try {
            $r->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            $roles = Role::where('company_id', $r->query('company_id'))
                ->select('id', 'key', 'name')
                ->orderBy('name')
                ->get();

            return $this->ok($roles, 'Daftar roles berhasil dimuat');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Format validation error agar konsisten
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[$field] = implode(', ', $messages);
            }

            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => 'Validasi gagal. Silakan periksa parameter company_id.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'Gagal memuat daftar roles',
            ], 500);
        }
    }

    /**
     * POST /api/auth/register
     * Registrasi user baru
     */
    public function register(Request $r)
    {
        try {
            $r->validate([
                'name' => 'required|string|max:120',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20|unique:user_profiles,phone',
                'password' => 'required|string|min:6',
                'company_id' => 'required|exists:companies,id',
                'department_id' => 'required|exists:departments,id',
                'position' => 'required|string|max:100',
                'gender' => 'required|string|in:Laki-laki,Perempuan,Male,Female',
                'no_ktp' => 'nullable|string|max:20|unique:user_profiles,no_ktp',
                'tanggal_lahir' => 'nullable|date',
                'role_id' => 'required|exists:roles,id',
            ]);

            // Generate ID dengan format: USR-{companyCode}-{number}
            // Contoh: CMP-LT → USR-LT-001, USR-LT-002, dst
            $companyCode = str_replace('CMP-', '', $r->company_id);

            // Generate user_id (untuk users.id dan user_profiles.id & user_profiles.user_id)
            $userId = $this->generateUserId($companyCode);

            // Map gender ke format database
            $gender = $this->mapGender($r->gender);

            DB::transaction(function () use ($r, $userId, $gender) {
                // 1. Insert ke tabel users (hanya data login/auth)
                User::create([
                    'id' => $userId,
                    'company_id' => $r->company_id,
                    'email' => $r->email,
                    'password' => Hash::make($r->password),
                    'status' => 'archived',
                    'is_employee' => true,
                ]);

                // 2. Insert ke tabel user_profiles
                // id dan user_id harus sama dalam satu row
                UserProfile::create([
                    'id' => $userId,
                    'user_id' => $userId,
                    'name' => $r->name,
                    'phone' => $r->phone,
                    'department_id' => $r->department_id,
                    'position' => $r->position,
                    'gender' => $gender,
                    'no_ktp' => $r->no_ktp,
                    'tanggal_lahir' => $r->tanggal_lahir,
                ]);

                // 3. Insert ke tabel user_roles
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $r->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return $this->ok(['id' => $userId], 'Registrasi berhasil. Akun Anda sedang menunggu verifikasi.', [], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Build pesan error spesifik berdasarkan field yang gagal
            $errorMessage = $this->buildValidationErrorMessage($e->errors());

            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => $errorMessage,
            ], 422);
        } catch (Throwable $e) {
            report($e);

            // Cek jika error duplikat entry (untuk field unik)
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $errorMessage = $this->parseDuplicateEntryError($e->getMessage());

                return response()->json([
                    'success' => false,
                    'code' => 409,
                    'message' => $errorMessage,
                ], 409);
            }

            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'Terjadi kesalahan server. Silakan coba lagi nanti.',
            ], 500);
        }
    }

    /**
     * Build pesan error spesifik dari validation errors
     */
    private function buildValidationErrorMessage(array $errors): string
    {
        $messages = [];

        foreach ($errors as $field => $errorList) {
            $fieldName = $this->getFieldLabel($field);
            $errorText = is_array($errorList) ? implode(', ', $errorList) : $errorList;

            // Generate pesan spesifik per field
            $messages[] = $this->getSpecificErrorMessage($field, $fieldName, $errorText);
        }

        return implode('. ', array_filter($messages));
    }

    /**
     * Get label user-friendly untuk field
     */
    private function getFieldLabel(string $field): string
    {
        $labels = [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'password' => 'Password',
            'company_id' => 'Perusahaan',
            'department_id' => 'Departemen',
            'position' => 'Posisi',
            'gender' => 'Jenis kelamin',
            'no_ktp' => 'Nomor KTP',
            'tanggal_lahir' => 'Tanggal lahir',
            'role_id' => 'Role',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Get pesan error spesifik per field
     */
    private function getSpecificErrorMessage(string $field, string $fieldName, string $errorText): string
    {
        // Email duplikat
        if ($field === 'email' && str_contains($errorText, 'has already been taken')) {
            return 'Email sudah terdaftar, gunakan email lain';
        }

        // Phone duplikat
        if ($field === 'phone' && str_contains($errorText, 'has already been taken')) {
            return 'Nomor telepon sudah digunakan user lain';
        }

        // No KTP duplikat
        if ($field === 'no_ktp' && str_contains($errorText, 'has already been taken')) {
            return 'Nomor KTP sudah terdaftar';
        }

        // Password terlalu pendek
        if ($field === 'password' && str_contains($errorText, 'at least')) {
            return 'Password minimal 6 karakter';
        }

        // Generic error dengan field name
        return "$fieldName: $errorText";
    }

    /**
     * Parse duplicate entry error dari database
     */
    private function parseDuplicateEntryError(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'users.email')) {
            return 'Email sudah terdaftar, gunakan email lain';
        }

        if (str_contains($errorMessage, 'user_profiles.phone')) {
            return 'Nomor telepon sudah digunakan user lain';
        }

        if (str_contains($errorMessage, 'user_profiles.no_ktp')) {
            return 'Nomor KTP sudah terdaftar';
        }

        if (str_contains($errorMessage, 'users.PRIMARY')) {
            return 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }

        return 'Data sudah terdaftar. Silakan gunakan data lain.';
    }

    /**
     * Map gender dari input ke format database
     * Input: Laki-laki, Perempuan, Male, Female
     * Database: PRIA, WANITA
     */
    private function mapGender(string $gender): string
    {
        $genderLower = strtolower($gender);

        // Laki-laki / Male → PRIA
        if (in_array($genderLower, ['laki-laki', 'male', 'pria'])) {
            return 'PRIA';
        }

        // Perempuan / Female → WANITA
        if (in_array($genderLower, ['perempuan', 'female', 'wanita'])) {
            return 'WANITA';
        }

        // Default fallback
        return 'PRIA';
    }

    /**
     * Generate user ID dengan format: USR-{companyCode}-{number}
     * Contoh: USR-LT-001, USR-LT-002, dst
     *
     * @param string $companyCode Kode perusahaan (tanpa CMP-)
     * @return string User ID baru
     */
    private function generateUserId(string $companyCode): string
    {
        $prefix = "USR-{$companyCode}-";

        // Cari user ID terakhir dengan prefix yang sama
        // Hanya yang formatnya numeric (USR-LT-XXX, bukan USR-LT-SLS7)
        $lastUser = User::whereRaw("id LIKE ?", [$prefix . '%'])
            ->whereRaw("id REGEXP ?", [$prefix . '[0-9]+$'])  // Hanya numeric suffix
            ->orderBy('id', 'desc')
            ->first();

        if ($lastUser) {
            // Extract nomor dari ID terakhir (contoh: USR-LT-001 → 001)
            $lastNumber = (int) str_replace($prefix, '', $lastUser->id);
            $newNumber = $lastNumber + 1;

            Log::info('generateUserId: found last user', [
                'prefix' => $prefix,
                'lastUserId' => $lastUser->id,
                'lastNumber' => $lastNumber,
                'newNumber' => $newNumber,
            ]);
        } else {
            // Jika belum ada user dengan format numeric, cari max number dari semua
            $maxNumber = User::whereRaw("id LIKE ?", [$prefix . '%'])
                ->whereRaw("id REGEXP ?", [$prefix . '[0-9]+$'])
                ->get()
                ->map(function ($user) use ($prefix) {
                    return (int) str_replace($prefix, '', $user->id);
                })
                ->max();

            $newNumber = $maxNumber ? $maxNumber + 1 : 1;

            Log::info('generateUserId: calculated max number', [
                'prefix' => $prefix,
                'maxNumber' => $maxNumber,
                'newNumber' => $newNumber,
            ]);
        }

        // Format dengan leading zero (3 digit)
        $newUserId = sprintf("USR-%s-%03d", $companyCode, $newNumber);

        Log::info('generateUserId: result', [
            'newUserId' => $newUserId,
            'companyCode' => $companyCode,
        ]);

        return $newUserId;
    }

    /**
     * Increment user ID
     * Contoh: USR-LT-001 → USR-LT-002
     *
     * @param string $userId User ID yang akan di-increment
     * @return string User ID baru
     */
    private function incrementUserId(string $userId): string
    {
        // Extract nomor dari ID (contoh: USR-LT-001 → 001)
        $parts = explode('-', $userId);
        $number = (int) end($parts);

        // Increment dan format ulang
        $newNumber = $number + 1;
        $parts[count($parts) - 1] = sprintf('%03d', $newNumber);

        return implode('-', $parts);
    }
}
