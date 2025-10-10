<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PermissionsMasterController extends Controller
{
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('permissions:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $data=DB::table('permissions')->orderBy('key')->get();
            return $this->ok($data,'Permissions master');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat permissions',500,'SERVER_ERROR'); }
    }
}
