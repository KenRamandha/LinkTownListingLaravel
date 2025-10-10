<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesCustomersController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.customer:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $q = DB::table('customers')->where('company_id', $u->company_id);
            if ($s = $r->query('q')) {
                $q->where(function($qq) use ($s){
                    $qq->where('name','like',"%$s%")
                       ->orWhere('email','like',"%$s%")
                       ->orWhere('phone','like',"%$s%");
                });
            }
            $data = $q->orderBy('name')->limit((int)$r->query('limit',50))->get();
            return $this->ok($data, 'Customers');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat customers', 500, 'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.customer:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'type' => 'required|in:individual,company',
                'name' => 'required|string|max:150',
                'email' => 'nullable|email|max:120',
                'phone' => 'nullable|string|max:50',
                'tax_no'=> 'nullable|string|max:60',
                'address'=> 'nullable|string'
            ]);
            $u = $r->user();
            $id = (string) Str::orderedUuid();
            DB::table('customers')->insert([
                'id'=>$id,
                'company_id'=>$u->company_id,
                'type'=>$r->type,
                'name'=>$r->name,
                'email'=>$r->email,
                'phone'=>$r->phone,
                'tax_no'=>$r->tax_no,
                'address'=>$r->address,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id], 'Customer dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat customer', 500, 'SERVER_ERROR'); }
    }

    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.customer:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('customers')->where('company_id',$u->company_id)->where('id',$id)->first();
            if (!$data) return $this->fail('Customer tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Customer');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat customer', 500, 'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.customer:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'type' => 'nullable|in:individual,company',
                'name' => 'nullable|string|max:150',
                'email' => 'nullable|email|max:120',
                'phone' => 'nullable|string|max:50',
                'tax_no'=> 'nullable|string|max:60',
                'address'=> 'nullable|string'
            ]);
            $u = $r->user();
            $exists = DB::table('customers')->where('company_id',$u->company_id)->where('id',$id)->exists();
            if (!$exists) return $this->fail('Customer tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('type','name','email','phone','tax_no','address'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('customers')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id], 'Customer diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui customer', 500, 'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.customer:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $count = DB::table('customers')->where('company_id',$u->company_id)->where('id',$id)->delete();
            if (!$count) return $this->fail('Customer tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Customer dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus customer', 500, 'SERVER_ERROR'); }
    }
}
