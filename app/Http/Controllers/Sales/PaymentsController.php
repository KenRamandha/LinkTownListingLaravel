<?php


namespace App\Http\Controllers\Sales;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentsController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.payment:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $q = DB::table('payments');
            if ($inv = $r->query('invoice_id')) $q->where('invoice_id', $inv);
            if ($m = $r->query('method')) $q->where('method', $m);
            $data = $q->orderByDesc('pay_date')->limit((int)$r->query('limit', 50))->get();
            return $this->ok($data, 'Payments');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat payments', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.payment:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'invoice_id' => 'required|string',
                'pay_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'method' => "required|in:cash,transfer,credit_card,ewallet",
                'reference_no' => 'nullable|string|max:100'
            ]);
            $id = (string)Str::orderedUuid();
            DB::table('payments')->insert([
                'id' => $id,
                'invoice_id' => $r->invoice_id,
                'pay_date' => $r->pay_date,
                'amount' => $r->amount,
                'method' => $r->method,
                'reference_no' => $r->reference_no,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Payment dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat payment', 500, 'SERVER_ERROR');
        }
    }
    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.payment:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('payments')->where('id', $id)->first();
            if (!$data) return $this->fail('Payment tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Payment');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat payment', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.payment:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'pay_date' => 'nullable|date',
                'amount' => 'nullable|numeric|min:0',
                'method' => "nullable|in:cash,transfer,credit_card,ewallet",
                'reference_no' => 'nullable|string|max:100'
            ]);
            $exists = DB::table('payments')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Payment tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('pay_date', 'amount', 'method', 'reference_no'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('payments')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Payment diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui payment', 500, 'SERVER_ERROR');
        }
    }
}
