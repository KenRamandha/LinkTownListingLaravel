<?php


namespace App\Http\Controllers\Sales;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ContractsController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.contract:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $q = DB::table('contracts');
            if ($so = $r->query('sales_order_id')) $q->where('sales_order_id', $so);
            $data = $q->orderByDesc('created_at')->limit((int)$r->query('limit', 50))->get();
            return $this->ok($data, 'Contracts');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat contracts', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.contract:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'sales_order_id' => 'required|string',
                'contract_no' => 'required|string|max:60',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'terms' => 'nullable|string'
            ]);
            $id = (string)Str::orderedUuid();
            DB::table('contracts')->insert([
                'id' => $id,
                'sales_order_id' => $r->sales_order_id,
                'contract_no' => $r->contract_no,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'terms' => $r->terms,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Contract dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat contract', 500, 'SERVER_ERROR');
        }
    }
    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.contract:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('contracts')->where('id', $id)->first();
            if (!$data) return $this->fail('Contract tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Contract');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat contract', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.contract:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'contract_no' => 'nullable|string|max:60',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'terms' => 'nullable|string'
            ]);
            $exists = DB::table('contracts')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Contract tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('contract_no', 'start_date', 'end_date', 'terms'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('contracts')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Contract diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui contract', 500, 'SERVER_ERROR');
        }
    }
}
