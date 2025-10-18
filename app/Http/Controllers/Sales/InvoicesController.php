<?php


namespace App\Http\Controllers\Sales;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class InvoicesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.invoice:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $q = DB::table('invoices');
            if ($so = $r->query('sales_order_id')) $q->where('sales_order_id', $so);
            if ($st = $r->query('status')) $q->where('status', $st);
            $data = $q->orderByDesc('issue_date')->limit((int)$r->query('limit', 50))->get();
            return $this->ok($data, 'Invoices');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat invoices', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.invoice:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'sales_order_id' => 'required|string',
                'invoice_no' => 'required|string|max:60',
                'issue_date' => 'required|date',
                'due_date' => 'required|date',
                'amount_total' => 'required|numeric|min:0',
                'status' => "required|in:unpaid,partial,paid,void",
            ]);
            $id = (string)Str::orderedUuid();
            DB::table('invoices')->insert([
                'id' => $id,
                'sales_order_id' => $r->sales_order_id,
                'invoice_no' => $r->invoice_no,
                'issue_date' => $r->issue_date,
                'due_date' => $r->due_date,
                'amount_total' => $r->amount_total,
                'status' => $r->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Invoice dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat invoice', 500, 'SERVER_ERROR');
        }
    }
    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.invoice:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('invoices')->where('id', $id)->first();
            if (!$data) return $this->fail('Invoice tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Invoice');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat invoice', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.invoice:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'invoice_no' => 'nullable|string|max:60',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'amount_total' => 'nullable|numeric|min:0',
                'status' => "nullable|in:unpaid,partial,paid,void",
            ]);
            $exists = DB::table('invoices')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Invoice tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('invoice_no', 'issue_date', 'due_date', 'amount_total', 'status'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('invoices')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Invoice diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui invoice', 500, 'SERVER_ERROR');
        }
    }
}
