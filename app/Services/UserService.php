<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Exception;

class UserService
{
    /**
     * Get user list for DataTables
     */
    public function getListData(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('users.company_id', $companyId)
            ->select([
                'users.id',
                'users.email',
                'user_profiles.avatar_url',
                'users.status',
                'users.is_employee',
                'users.created_at',
                'users.last_login_at',
                'user_profiles.name',
                'user_profiles.position',
                'roles.name as role_name'
            ]);

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('user_profiles.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('user_profiles.position', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order.0.column')) {
            $columns = ['user_profiles.name', 'user_profiles.position', 'users.status', 'users.created_at'];
            $columnIndex = $request->input('order.0.column');
            $direction = $request->input('order.0.dir');
            if (isset($columns[$columnIndex])) {
                $query->orderBy($columns[$columnIndex], $direction);
            }
        } else {
            $query->orderBy('user_profiles.name', 'asc');
        }

        return $query->get();
    }

    /**
     * Create a new user
     */
    public function createUser(array $data, ?Request $request = null)
    {
        DB::beginTransaction();

        try {
            $company = DB::table('companies')
                ->where('id', $data['company_id'])
                ->first();

            if (!$company) {
                throw new Exception('Company not found');
            }

            $companyCode = $company->code;

            // Generate User ID
            $lastUser = DB::table('users')
                ->where('company_id', $data['company_id'])
                ->where('id', 'like', 'USR-' . $companyCode . '-%')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $counter = $lastUser ? (int) substr($lastUser->id, -3) : 0;

            do {
                $counter++;
                $userId = 'USR-' . $companyCode . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
                $exists = DB::table('users')->where('id', $userId)->exists();
            } while ($exists);

            // Generate Employee Code
            $lastProfile = DB::table('user_profiles')
                ->where('employee_code', 'like', 'EMP-' . $companyCode . '-%')
                ->orderBy('employee_code', 'desc')
                ->lockForUpdate()
                ->first();

            $empCounter = $lastProfile ? (int) substr($lastProfile->employee_code, -3) : 0;

            do {
                $empCounter++;
                $employeeCode = 'EMP-' . $companyCode . '-' . str_pad($empCounter, 3, '0', STR_PAD_LEFT);
                $empExists = DB::table('user_profiles')->where('employee_code', $employeeCode)->exists();
            } while ($empExists);

            // Insert User
            DB::table('users')->insert([
                'id' => $userId,
                'company_id' => $data['company_id'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_employee' => isset($data['is_employee']) ? 1 : 0,
                'status' => $data['status'],
                'akses_web' => $data['akses_web'] ?? 'NO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $avatarUrl = null;
            if ($request && $request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $filename = time() . '_' . $file->getClientOriginalName();
                $uploadPath = public_path('uploads/users');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                $file->move($uploadPath, $filename);
                $avatarUrl = '/uploads/users/' . $filename;
            }

            // Insert Profile
            DB::table('user_profiles')->insert([
                'id' => $userId,
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'avatar_url' => $avatarUrl,
                'department_id' => $data['department_id'] ?? null,
                'position' => $data['position'] ?? null,
                'join_date' => $data['join_date'] ?: null,
                'resign_date' => $data['resign_date'] ?: null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                'mulai_pkwt' => $data['mulai_pkwt'] ?: null,
                'akhir_pkwt' => $data['akhir_pkwt'] ?: null,
                'no_ktp' => $data['no_ktp'] ?? null,
                'alamat_ktp' => $data['alamat_ktp'] ?? null,
                'gender' => $data['gender'] ?: null,
                'stat_nikah' => $data['stat_nikah'] ?? null,
                'stat_pajak' => $data['stat_pajak'] ?? null,
                'stat_karyawan' => $data['stat_karyawan'] ?? null,
                'no_npwp' => $data['no_npwp'] ?? null,
                'no_bpjs_sehat' => $data['no_bpjs_sehat'] ?? null,
                'no_bpjs_kerja' => $data['no_bpjs_kerja'] ?? null,
                'no_kontrak' => $data['no_kontrak'] ?? null,
                'no_pkwt' => $data['no_pkwt'] ?? null,
                'bank' => $data['bank'] ?? null,
                'no_rekening' => $data['no_rekening'] ?: 0,
                'nama_rekening' => $data['nama_rekening'] ?? null,
                'izin_cuti' => $data['izin_cuti'] ?: 0,
                'izin_telat' => $data['izin_telat'] ?: 0,
                'izin_masuk' => $data['izin_masuk'] ?: 0,
                'izin_pulang' => $data['izin_pulang'] ?: 0,
                'gaji_pokok' => $data['gaji_pokok'] ?: 0,
                'lembur' => $data['lembur'] ?: 0,
                'transport' => $data['transport'] ?: 0,
                'thr' => $data['thr'] ?: 0,
                'kehadiran' => $data['kehadiran'] ?: 0,
                'bonus_pribadi' => $data['bonus_pribadi'] ?: 0,
                'bonus_team' => $data['bonus_team'] ?: 0,
                'pot_izin' => $data['pot_izin'] ?: 0,
                'pot_mangkir' => $data['pot_mangkir'] ?: 0,
                'pot_telat' => $data['pot_telat'] ?: 0,
                'pot_kasbon' => $data['pot_kasbon'] ?: 0,
                'pot_bpjs_sehat' => $data['pot_bpjs_sehat'] ?: 0,
                'pot_bpjs_kerja' => $data['pot_bpjs_kerja'] ?: 0,
                'tunjangan_bpjs_sehat' => $data['tunjangan_bpjs_sehat'] ?: 0,
                'tunjangan_bpjs_kerja' => $data['tunjangan_bpjs_kerja'] ?: 0,
                'tunjangan_pajak' => $data['tunjangan_pajak'] ?: 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Handle Role
            if (!empty($data['role_id'])) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $data['role_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Handle Permissions
            if (($data['akses_web'] ?? 'NO') == 'YES' && isset($data['menu_permissions'])) {
                $this->savePermissions($userId, $data['role_id'] ?? null, $data['user_companies'] ?? [$data['company_id']], $data['menu_permissions']);
            }

            DB::commit();

            return [
                'user_id' => $userId,
                'employee_code' => $employeeCode
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser($id, array $data, ?Request $request = null)
    {
        DB::beginTransaction();

        try {
            $userUpdate = [
                'company_id' => $data['company_id'],
                'email' => $data['email'],
                'is_employee' => isset($data['is_employee']) ? 1 : 0,
                'status' => $data['status'],
                'akses_web' => $data['akses_web'] ?? 'NO',
                'updated_at' => now(),
            ];

            if (!empty($data['password'])) {
                $userUpdate['password'] = Hash::make($data['password']);
            }

            DB::table('users')->where('id', $id)->update($userUpdate);

            $profileUpdate = [
                'employee_code' => $data['employee_code'],
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'position' => $data['position'] ?? null,
                'join_date' => $data['join_date'] ?: null,
                'resign_date' => $data['resign_date'] ?: null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                'mulai_pkwt' => $data['mulai_pkwt'] ?: null,
                'akhir_pkwt' => $data['akhir_pkwt'] ?: null,
                'no_ktp' => $data['no_ktp'] ?? null,
                'alamat_ktp' => $data['alamat_ktp'] ?? null,
                'gender' => $data['gender'] ?: null,
                'stat_nikah' => $data['stat_nikah'] ?? null,
                'stat_pajak' => $data['stat_pajak'] ?? null,
                'stat_karyawan' => $data['stat_karyawan'] ?? null,
                'no_npwp' => $data['no_npwp'] ?? null,
                'no_bpjs_sehat' => $data['no_bpjs_sehat'] ?? null,
                'no_bpjs_kerja' => $data['no_bpjs_kerja'] ?? null,
                'no_kontrak' => $data['no_kontrak'] ?? null,
                'no_pkwt' => $data['no_pkwt'] ?? null,
                'bank' => $data['bank'] ?? null,
                'no_rekening' => $data['no_rekening'] ?: 0,
                'nama_rekening' => $data['nama_rekening'] ?? null,
                'izin_cuti' => $data['izin_cuti'] ?: 0,
                'izin_telat' => $data['izin_telat'] ?: 0,
                'izin_masuk' => $data['izin_masuk'] ?: 0,
                'izin_pulang' => $data['izin_pulang'] ?: 0,
                'gaji_pokok' => $data['gaji_pokok'] ?: 0,
                'lembur' => $data['lembur'] ?: 0,
                'transport' => $data['transport'] ?: 0,
                'thr' => $data['thr'] ?: 0,
                'kehadiran' => $data['kehadiran'] ?: 0,
                'bonus_pribadi' => $data['bonus_pribadi'] ?: 0,
                'bonus_team' => $data['bonus_team'] ?: 0,
                'pot_izin' => $data['pot_izin'] ?: 0,
                'pot_mangkir' => $data['pot_mangkir'] ?: 0,
                'pot_telat' => $data['pot_telat'] ?: 0,
                'pot_kasbon' => $data['pot_kasbon'] ?: 0,
                'pot_bpjs_sehat' => $data['pot_bpjs_sehat'] ?: 0,
                'pot_bpjs_kerja' => $data['pot_bpjs_kerja'] ?: 0,
                'tunjangan_bpjs_sehat' => $data['tunjangan_bpjs_sehat'] ?: 0,
                'tunjangan_bpjs_kerja' => $data['tunjangan_bpjs_kerja'] ?: 0,
                'tunjangan_pajak' => $data['tunjangan_pajak'] ?: 0,
                'updated_at' => now(),
            ];

            if ($request && $request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $filename = time() . '_' . $file->getClientOriginalName();
                $uploadPath = public_path('uploads/users');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                $file->move($uploadPath, $filename);
                $profileUpdate['avatar_url'] = '/uploads/users/' . $filename;
            }

            DB::table('user_profiles')->where('user_id', $id)->update($profileUpdate);

            if (!empty($data['role_id'])) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $id],
                    [
                        'role_id' => $data['role_id'],
                        'updated_at' => now(),
                        'created_at' => now()
                    ]
                );
            }

            DB::table('config_main_menu_permission')->where('user_id', $id)->delete();
            DB::table('config_sub_menu_permission')->where('user_id', $id)->delete();

            if (($data['akses_web'] ?? 'NO') == 'YES' && isset($data['menu_permissions'])) {
                $roleId = $data['role_id'] ?? null;
                if (empty($roleId)) {
                    $existingRole = DB::table('user_roles')->where('user_id', $id)->first();
                    $roleId = $existingRole ? $existingRole->role_id : null;
                }

                $this->savePermissions($id, $roleId, $data['user_companies'] ?? [$data['company_id']], $data['menu_permissions']);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser($id)
    {
        DB::beginTransaction();
        try {
            DB::table('config_main_menu_permission')->where('user_id', $id)->delete();
            DB::table('config_sub_menu_permission')->where('user_id', $id)->delete();
            DB::table('shifts_mapping')->where('user_id', $id)->delete();
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('user_profiles')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get shift mappings for a user
     */
    public function getShiftMappings($userId)
    {
        return DB::table('shifts_mapping')
            ->join('shifts', 'shifts_mapping.shift_id', '=', 'shifts.id')
            ->where('shifts_mapping.user_id', $userId)
            ->select('shifts_mapping.*', 'shifts.name as shift_name')
            ->orderBy('shifts_mapping.work_date', 'desc')
            ->get();
    }

    /**
     * Store shift mapping for a user
     */
    public function storeShiftMapping($userId, array $data)
    {
        DB::beginTransaction();
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                throw new Exception('User not found');
            }

            $prefix = $userId . '-' . $user->company_id . '-';

            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);

            DB::table('shifts_mapping')
                ->where('user_id', $userId)
                ->whereBetween('work_date', [$data['start_date'], $data['end_date']])
                ->delete();

            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

            foreach ($period as $date) {
                $workDate = $date->format('Y-m-d');

                $lastMapping = DB::table('shifts_mapping')
                    ->where('id', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                $counter = $lastMapping ? (int) substr($lastMapping->id, -3) : 0;
                $counter++;
                $mappingId = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);

                DB::table('shifts_mapping')->insert([
                    'id' => $mappingId,
                    'user_id' => $userId,
                    'shift_id' => $data['shift_id'],
                    'work_date' => $workDate,
                    'lock_location' => $data['lock_location'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get attachments for a user
     */
    public function getAttachments($userId)
    {
        return DB::table('tr_attachments')
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * Store attachment for a user
     */
    public function storeAttachment($userId, Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $docType = $request->doc_type;
            $fileName = $docType . '-' . $originalName;

            $path = $file->storeAs('attachments/' . $userId, $fileName, 'public');

            DB::table('tr_attachments')->insert([
                'user_id' => $userId,
                'doc_type' => $docType,
                'file_origin' => $originalName,
                'file_final' => $fileName,
                'file_path' => $path,
                'created_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment($id)
    {
        $attachment = DB::table('tr_attachments')->where('id', $id)->first();

        if ($attachment) {
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            DB::table('tr_attachments')->where('id', $id)->delete();
            return true;
        }

        return false;
    }

    /**
     * Get data for adding a user
     */
    public function getAddData()
    {
        $menus = $this->getMenuTreeWithPermissions();
        $roles = DB::table('roles')->select('id', 'name')->get();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();
        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();
        $doc_types = DB::table('ms_user_status')->where('stat_type', 'DOCUMENT')->get();

        return compact('menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats', 'doc_types');
    }

    /**
     * Get data for editing a user
     */
    public function getEditData($id)
    {
        $user = DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select('users.*', 'user_profiles.*', 'user_roles.role_id', 'users.id as id')
            ->first();

        if (!$user) {
            return null;
        }

        $menus = $this->getMenuTreeWithPermissions($id);
        $roles = DB::table('roles')->select('id', 'name')->get();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();
        $shifts = DB::table('shifts')->where('company_id', $user->company_id)->select('id', 'name', 'start_time', 'end_time')->get();

        $permissionRow = DB::table('config_main_menu_permission')
            ->where('user_id', $id)
            ->first();

        $userCompanies = [];
        if ($permissionRow) {
            $userCompanies = $permissionRow->company_ids;
            if (is_string($userCompanies)) {
                $userCompanies = json_decode($userCompanies, true) ?: [];
            }
        }

        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();
        $doc_types = DB::table('ms_user_status')->where('stat_type', 'DOCUMENT')->get();

        return compact('user', 'menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats', 'shifts', 'doc_types', 'userCompanies');
    }

    /**
     * Save menu permissions
     */
    private function savePermissions($userId, $roleId, $companyIds, array $menuPermissions)
    {
        $companyIdsJson = json_encode($companyIds);

        foreach ($menuPermissions as $type => $menus) {
            foreach ($menus as $menuId => $perms) {
                if ($type === 'main') {
                    DB::table('config_main_menu_permission')->insert([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'company_ids' => $companyIdsJson,
                        'main_menu_id' => $menuId,
                        'can_view' => isset($perms['view']) ? 1 : 0,
                        'can_create' => isset($perms['create']) ? 1 : 0,
                        'can_update' => isset($perms['update']) ? 1 : 0,
                        'can_delete' => isset($perms['delete']) ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('config_sub_menu_permission')->insert([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'company_ids' => $companyIdsJson,
                        'sub_menu_id' => $menuId,
                        'can_view' => isset($perms['view']) ? 1 : 0,
                        'can_create' => isset($perms['create']) ? 1 : 0,
                        'can_update' => isset($perms['update']) ? 1 : 0,
                        'can_delete' => isset($perms['delete']) ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Get menu tree with permissions for a user
     */
    public function getMenuTreeWithPermissions($userId = null)
    {
        $mainMenus = DB::table('config_main_menu')
            ->where('is_active', 1)
            ->orderBy('menu_order')
            ->get();

        foreach ($mainMenus as $menu) {
            if ($userId) {
                $menu->permission = DB::table('config_main_menu_permission')
                    ->where('user_id', $userId)
                    ->where('main_menu_id', $menu->id)
                    ->first();
            }

            $subMenus = DB::table('config_sub_menu')
                ->where('main_menu_id', $menu->id)
                ->where('is_active', 1)
                ->orderBy('menu_order')
                ->get();

            if ($userId) {
                foreach ($subMenus as $sub) {
                    $sub->permission = DB::table('config_sub_menu_permission')
                        ->where('user_id', $userId)
                        ->where('sub_menu_id', $sub->id)
                        ->first();
                }
            }

            $menu->tree = $subMenus;
        }

        return $mainMenus;
    }
}
