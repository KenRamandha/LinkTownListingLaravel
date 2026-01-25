<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('core.users.index');
    }

    public function getList(Request $request)
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

        return response()->json([
            'data' => $query->get()
        ]);
    }

    public function add()
    {
        $menus = $this->getMenuTree();
        $roles = DB::table('roles')->select('id', 'name')->get();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();

        $userCompanies = [];

        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();
        $doc_types = DB::table('ms_user_status')->where('stat_type', 'DOCUMENT')->get();

        return view('core.users.add', compact('menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats', 'doc_types', 'userCompanies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'name' => 'required|string|max:120',
            'company_id' => 'required',
            'status' => 'required',
            'join_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'tanggal_lahir' => 'nullable|date',
            'gender' => 'nullable|in:PRIA,WANITA',
        ]);

        DB::beginTransaction();

        try {
            $company = DB::table('companies')
                ->where('id', $request->company_id)
                ->first();

            if (!$company) {
                throw new \Exception('Company not found');
            }

            $companyCode = $company->code;

            $lastUser = DB::table('users')
                ->where('company_id', $request->company_id)
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

            DB::table('users')->insert([
                'id' => $userId,
                'company_id' => $request->company_id,
                'email' => $request->email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                'is_employee' => $request->has('is_employee') ? 1 : 0,
                'status' => $request->status,
                'akses_web' => $request->akses_web ?? 'NO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $avatarUrl = null;
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $filename = time() . '_' . $file->getClientOriginalName();
                $uploadPath = public_path('uploads/users');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                $file->move($uploadPath, $filename);
                $avatarUrl = '/uploads/users/' . $filename;
            }

            DB::table('user_profiles')->insert([
                'id' => $userId,
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'name' => $request->name,
                'phone' => $request->phone,
                'avatar_url' => $avatarUrl,
                'department_id' => $request->department_id,
                'position' => $request->position,
                'join_date' => $request->join_date ?: null,
                'resign_date' => $request->resign_date ?: null,
                'tanggal_lahir' => $request->tanggal_lahir ?: null,
                'mulai_pkwt' => $request->mulai_pkwt ?: null,
                'akhir_pkwt' => $request->akhir_pkwt ?: null,
                'no_ktp' => $request->no_ktp,
                'alamat_ktp' => $request->alamat_ktp,
                'gender' => $request->gender ?: null,
                'stat_nikah' => $request->stat_nikah,
                'stat_pajak' => $request->stat_pajak,
                'stat_karyawan' => $request->stat_karyawan,
                'no_npwp' => $request->no_npwp,
                'no_bpjs_sehat' => $request->no_bpjs_sehat,
                'no_bpjs_kerja' => $request->no_bpjs_kerja,
                'no_kontrak' => $request->no_kontrak,
                'no_pkwt' => $request->no_pkwt,
                'bank' => $request->bank,
                'no_rekening' => $request->no_rekening ?: 0,
                'nama_rekening' => $request->nama_rekening,
                'izin_cuti' => $request->izin_cuti ?: 0,
                'izin_telat' => $request->izin_telat ?: 0,
                'izin_masuk' => $request->izin_masuk ?: 0,
                'izin_pulang' => $request->izin_pulang ?: 0,
                'gaji_pokok' => $request->gaji_pokok ?: 0,
                'lembur' => $request->lembur ?: 0,
                'transport' => $request->transport ?: 0,
                'thr' => $request->thr ?: 0,
                'kehadiran' => $request->kehadiran ?: 0,
                'bonus_pribadi' => $request->bonus_pribadi ?: 0,
                'bonus_team' => $request->bonus_team ?: 0,
                'pot_izin' => $request->pot_izin ?: 0,
                'pot_mangkir' => $request->pot_mangkir ?: 0,
                'pot_telat' => $request->pot_telat ?: 0,
                'pot_kasbon' => $request->pot_kasbon ?: 0,
                'pot_bpjs_sehat' => $request->pot_bpjs_sehat ?: 0,
                'pot_bpjs_kerja' => $request->pot_bpjs_kerja ?: 0,
                'tunjangan_bpjs_sehat' => $request->tunjangan_bpjs_sehat ?: 0,
                'tunjangan_bpjs_kerja' => $request->tunjangan_bpjs_kerja ?: 0,
                'tunjangan_pajak' => $request->tunjangan_pajak ?: 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->filled('role_id')) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $request->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->akses_web == 'YES' && $request->has('menu_permissions')) {
                $roleId = $request->role_id;
                if (empty($roleId)) {
                    $existingRole = DB::table('user_roles')->where('user_id', $userId)->first();
                    $roleId = $existingRole ? $existingRole->role_id : null;
                }

                $companyIds = json_encode($request->user_companies ?? [$request->company_id]);

                foreach ($request->menu_permissions as $type => $menus) {
                    foreach ($menus as $menuId => $perms) {
                        if ($type === 'main') {
                            DB::table('config_main_menu_permission')->insert([
                                'user_id' => $userId,
                                'role_id' => $roleId,
                                'company_ids' => $companyIds,
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
                                'company_ids' => $companyIds,
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

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'user_id' => $userId,
                'employee_code' => $employeeCode
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }
    public function edit($id)
    {
        $user = DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select('users.*', 'user_profiles.*', 'user_roles.role_id', 'users.id as id')
            ->first();

        if (!$user) {
            return abort(404);
        }

        $menus = $this->getMenuTree($id);
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
        $userCompanies = is_array($userCompanies) ? $userCompanies : [];

        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();
        $doc_types = DB::table('ms_user_status')->where('stat_type', 'DOCUMENT')->get();

        return view('core.users.add', compact('user', 'menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats', 'shifts', 'doc_types', 'userCompanies'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'name' => 'required|string|max:120',
            'company_id' => 'required',
            'status' => 'required',
            'join_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'tanggal_lahir' => 'nullable|date',
            'gender' => 'nullable|in:PRIA,WANITA',
        ]);

        try {
            DB::beginTransaction();

            $userUpdate = [
                'company_id' => $request->company_id,
                'email' => $request->email,
                'is_employee' => $request->has('is_employee') ? 1 : 0,
                'status' => $request->status,
                'akses_web' => $request->akses_web ?? 'NO',
                'updated_at' => now(),
            ];

            if ($request->filled('password')) {
                $userUpdate['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
            }

            DB::table('users')->where('id', $id)->update($userUpdate);

            $profileUpdate = [
                'employee_code' => $request->employee_code,
                'name' => $request->name,
                'phone' => $request->phone,
                'department_id' => $request->department_id,
                'position' => $request->position,
                'join_date' => $request->join_date ?: null,
                'resign_date' => $request->resign_date ?: null,
                'tanggal_lahir' => $request->tanggal_lahir ?: null,
                'mulai_pkwt' => $request->mulai_pkwt ?: null,
                'akhir_pkwt' => $request->akhir_pkwt ?: null,
                'no_ktp' => $request->no_ktp,
                'alamat_ktp' => $request->alamat_ktp,
                'gender' => $request->gender ?: null,
                'stat_nikah' => $request->stat_nikah,
                'stat_pajak' => $request->stat_pajak,
                'stat_karyawan' => $request->stat_karyawan,
                'no_npwp' => $request->no_npwp,
                'no_bpjs_sehat' => $request->no_bpjs_sehat,
                'no_bpjs_kerja' => $request->no_bpjs_kerja,
                'no_kontrak' => $request->no_kontrak,
                'no_pkwt' => $request->no_pkwt,
                'bank' => $request->bank,
                'no_rekening' => $request->no_rekening ?: 0,
                'nama_rekening' => $request->nama_rekening,
                'izin_cuti' => $request->izin_cuti ?: 0,
                'izin_telat' => $request->izin_telat ?: 0,
                'izin_masuk' => $request->izin_masuk ?: 0,
                'izin_pulang' => $request->izin_pulang ?: 0,
                'gaji_pokok' => $request->gaji_pokok ?: 0,
                'lembur' => $request->lembur ?: 0,
                'transport' => $request->transport ?: 0,
                'thr' => $request->thr ?: 0,
                'kehadiran' => $request->kehadiran ?: 0,
                'bonus_pribadi' => $request->bonus_pribadi ?: 0,
                'bonus_team' => $request->bonus_team ?: 0,
                'pot_izin' => $request->pot_izin ?: 0,
                'pot_mangkir' => $request->pot_mangkir ?: 0,
                'pot_telat' => $request->pot_telat ?: 0,
                'pot_kasbon' => $request->pot_kasbon ?: 0,
                'pot_bpjs_sehat' => $request->pot_bpjs_sehat ?: 0,
                'pot_bpjs_kerja' => $request->pot_bpjs_kerja ?: 0,
                'tunjangan_bpjs_sehat' => $request->tunjangan_bpjs_sehat ?: 0,
                'tunjangan_bpjs_kerja' => $request->tunjangan_bpjs_kerja ?: 0,
                'tunjangan_pajak' => $request->tunjangan_pajak ?: 0,
                'updated_at' => now(),
            ];

            if ($request->hasFile('avatar')) {
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

            if ($request->filled('role_id')) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $id],
                    [
                        'role_id' => $request->role_id,
                        'updated_at' => now(),
                        'created_at' => now()
                    ]
                );
            }

            DB::table('config_main_menu_permission')->where('user_id', $id)->delete();
            DB::table('config_sub_menu_permission')->where('user_id', $id)->delete();

            if ($request->akses_web == 'YES' && $request->has('menu_permissions')) {
                $roleId = $request->role_id;

                if (empty($roleId)) {
                    $existingRole = DB::table('user_roles')->where('user_id', $id)->first();
                    $roleId = $existingRole ? $existingRole->role_id : null;
                }

                if ($roleId) {
                    $companyIds = json_encode($request->user_companies ?? [$request->company_id]);

                    foreach ($request->menu_permissions as $type => $menus) {
                        foreach ($menus as $menuId => $perms) {
                            if ($type === 'main') {
                                DB::table('config_main_menu_permission')->insert([
                                    'user_id' => $id,
                                    'role_id' => $roleId,
                                    'company_ids' => $companyIds,
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
                                    'user_id' => $id,
                                    'role_id' => $roleId,
                                    'company_ids' => $companyIds,
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
            }

            DB::commit();
            return response()->json(['message' => 'User updated successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            DB::table('config_main_menu_permission')->where('user_id', $id)->delete();
            DB::table('config_sub_menu_permission')->where('user_id', $id)->delete();
            DB::table('shifts_mapping')->where('user_id', $id)->delete();
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('user_profiles')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
            DB::commit();

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }

    public function getMappings(Request $request, $userId)
    {
        $mappings = DB::table('shifts_mapping')
            ->join('shifts', 'shifts_mapping.shift_id', '=', 'shifts.id')
            ->where('shifts_mapping.user_id', $userId)
            ->select('shifts_mapping.*', 'shifts.name as shift_name')
            ->orderBy('shifts_mapping.work_date', 'desc')
            ->get();

        return response()->json(['data' => $mappings]);
    }

    public function storeMapping(Request $request, $userId)
    {
        $request->validate([
            'shift_id' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            DB::beginTransaction();

            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                throw new \Exception('User not found');
            }

            $prefix = $userId . '-' . $user->company_id . '-';

            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);

            DB::table('shifts_mapping')
                ->where('user_id', $userId)
                ->whereBetween('work_date', [$request->start_date, $request->end_date])
                ->delete();

            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

            foreach ($period as $date) {
                $workDate = $date->format('Y-m-d');

                $lastMapping = DB::table('shifts_mapping')
                    ->where('id', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                $counter = 0;
                if ($lastMapping) {
                    $lastId = $lastMapping->id;
                    $counter = (int) substr($lastId, -3);
                }

                $counter++;
                $mappingId = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);

                DB::table('shifts_mapping')->insert([
                    'id' => $mappingId,
                    'user_id' => $userId,
                    'shift_id' => $request->shift_id,
                    'work_date' => $workDate,
                    'lock_location' => $request->lock_location ?? 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Shift mapping saved successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save mapping: ' . $e->getMessage()], 500);
        }
    }

    public function destroyMapping($id)
    {
        try {
            DB::table('shifts_mapping')->where('id', $id)->delete();
            return response()->json(['message' => 'Mapping deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete mapping'], 500);
        }
    }

    public function getDepartmentsByCompany($company_id)
    {
        $departments = DB::table('departments')
            ->where('company_id', $company_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    private function getMenuTree($userId = null)
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

    public function getAttachments($userId)
    {
        $attachments = DB::table('tr_attachments')
            ->where('user_id', $userId)
            ->get();

        return response()->json($attachments);
    }

    public function storeAttachment(Request $request, $userId)
    {
        $request->validate([
            'doc_type' => 'required',
            'file' => 'required|file|max:10240',
        ]);

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

            return response()->json(['message' => 'Attachment uploaded successfully']);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function destroyAttachment($id)
    {
        $attachment = DB::table('tr_attachments')->where('id', $id)->first();

        if ($attachment) {
            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            DB::table('tr_attachments')->where('id', $id)->delete();
            return response()->json(['message' => 'Attachment deleted successfully']);
        }

        return response()->json(['message' => 'Attachment not found'], 404);
    }
}