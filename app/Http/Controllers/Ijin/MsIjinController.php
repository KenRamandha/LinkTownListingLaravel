<?php

namespace App\Http\Controllers\Ijin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MsIjinController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = DB::table('ms_ijin')
                ->orderBy('id', 'desc')
                ->get();

            return $this->ok($data, 'Daftar master ijin berhasil dimuat');
        } catch (\Exception $e) {
            report($e);
            return $this->fail('Gagal memuat data master ijin', 500, 'SERVER_ERROR');
        }
    }
}
