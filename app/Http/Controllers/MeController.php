<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MeController extends Controller
{
    public function show(Request $r)
    {
        try {
            return $this->ok($r->user()->only(['id','name','email','company_id','is_employee','status']));
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat profil', 500, 'SERVER_ERROR');
        }
    }

    public function permissions(Request $r)
    {
        try {
            $u = $r->user();
            $rolePerms = DB::table('role_permissions')
              ->join('permissions','permissions.id','=','role_permissions.permission_id')
              ->whereIn('role_id', $u->roles()->pluck('roles.id'))
              ->where('allow', true)
              ->pluck('permissions.key');

            $userOverrides = DB::table('user_permissions')
              ->join('permissions','permissions.id','=','user_permissions.permission_id')
              ->where('user_id', $u->id)
              ->select('permissions.key','user_permissions.allow')->get();

            $eff = collect($rolePerms)->flip()->map(fn()=>true)->toArray();
            foreach ($userOverrides as $ov) { $eff[$ov->key] = (bool)$ov->allow; }

            return $this->ok(['permissions'=>array_keys(array_filter($eff))]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat permissions', 500, 'SERVER_ERROR');
        }
    }
}
