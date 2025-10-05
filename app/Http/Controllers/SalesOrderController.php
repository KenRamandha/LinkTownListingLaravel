<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesOrderController extends Controller
{
  public function index(Request $r)
  {
    try {
      $u = $r->user();
      $data = DB::table('sales_orders')
        ->where('company_id', $u->company_id)
        ->orderByDesc('order_date')
        ->limit(50)
        ->get();

      return $this->ok($data, 'Sales orders loaded');
    } catch (Throwable $e) {
      report($e);
      return $this->fail('Gagal memuat sales orders', 500, 'SERVER_ERROR');
    }
  }

  public function store(Request $r)
  {
    try {
      $r->validate([
        'customer_id' => 'required|string',
        'order_type' => 'required|in:property_sale,property_rent,goods_sale,service_sale',
        'sales_id' => 'required|string',
        'order_date' => 'required|date',
        'items' => 'required|array|min:1',
        'items.*.listing_id' => 'nullable|string',
        'items.*.item_name' => 'nullable|string',
        'items.*.qty' => 'required|numeric|min:0.01',
        'items.*.unit_price' => 'required|numeric|min:0',
        'items.*.discount_amount' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string'
      ]);

      $u = $r->user();
      $id = (string) Str::orderedUuid();
      $orderNo = 'SO/' . strtoupper($u->company_id) . '/' . now()->format('Y') . '/' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

      DB::transaction(function () use ($r, $u, $id, $orderNo) {
        DB::table('sales_orders')->insert([
          'id' => $id,
          'company_id' => $u->company_id,
          'customer_id' => $r->customer_id,
          'order_no' => $orderNo,
          'order_type' => $r->order_type,
          'sales_id' => $r->sales_id,
          'order_date' => $r->order_date,
          'status' => 'draft',
          'notes' => $r->notes,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
        foreach ($r->items as $i) {
          $lineId = (string) Str::orderedUuid();
          $qty = (float)$i['qty'];
          $price = (float)$i['unit_price'];
          $disc = (float)($i['discount_amount'] ?? 0);
          $subtotal = ($qty * $price) - $disc;
          DB::table('sales_order_items')->insert([
            'id' => $lineId,
            'sales_order_id' => $id,
            'listing_id' => $i['listing_id'] ?? null,
            'item_name' => $i['item_name'] ?? null,
            'qty' => $qty,
            'unit_price' => $price,
            'discount_amount' => $disc,
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now()
          ]);
        }
      });

      return $this->ok(['id' => $id, 'order_no' => $orderNo, 'status' => 'draft'], 'Sales order dibuat', [], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
      throw $e;
    } catch (Throwable $e) {
      report($e);
      return $this->fail('Gagal membuat sales order', 500, 'SERVER_ERROR');
    }
  }
}
