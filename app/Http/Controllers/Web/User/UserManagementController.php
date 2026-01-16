<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();

        return view('core.users.add', compact('menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats'));
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

        $menus = $this->getMenuTree();
        $roles = DB::table('roles')->select('id', 'name')->get();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();

        $banks = DB::table('ms_user_status')->where('stat_type', 'BANK')->get();
        $marriage_stats = DB::table('ms_user_status')->where('stat_type', 'NIKAH')->get();
        $tax_stats = DB::table('ms_user_status')->where('stat_type', 'PAJAK')->get();
        $employee_stats = DB::table('ms_user_status')->where('stat_type', 'KARYAWAN')->get();

        return view('core.users.add', compact('user', 'menus', 'roles', 'companies', 'banks', 'marriage_stats', 'tax_stats', 'employee_stats'));
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

    public function getDepartmentsByCompany($company_id)
    {
        $departments = DB::table('departments')
            ->where('company_id', $company_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    private function getMenuTree()
    {
        $user = auth()->user();
        if (!$user)
            return [];

        $menus = DB::table('menus')
            ->where('company_id', $user->company_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        foreach ($menus as $menu) {
            $items = DB::table('menu_items')
                ->where('menu_id', $menu->id)
                ->orderBy('sort_order')
                ->get();
            $byId = [];
            foreach ($items as $item) {
                $byId[$item->id] = (array) $item;
                $byId[$item->id]['children'] = [];
            }

            $tree = [];
            foreach ($byId as $id => &$node) {
                if (empty($node['parent_id'])) {
                    $tree[] = &$node;
                } else {
                    if (isset($byId[$node['parent_id']])) {
                        $byId[$node['parent_id']]['children'][] = &$node;
                    }
                }
            }

            $menu->tree = $tree;
        }

        return $menus;
    }

}