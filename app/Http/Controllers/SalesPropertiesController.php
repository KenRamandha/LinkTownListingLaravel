<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesPropertiesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.property:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $q = DB::table('properties')->where('company_id',$u->company_id);
            if ($s = $r->query('q')) $q->where('title','like',"%$s%");
            $data = $q->orderBy('title')->limit((int)$r->query('limit',50))->get();
            return $this->ok($data, 'Properties');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat properties', 500, 'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.property:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'code' => 'required|string|max:60',
                'title'=> 'required|string|max:150',
                'description'=> 'nullable|string',
                'type' => 'required|in:house,apartment,shop,land,warehouse,other',
                'address' => 'required|string',
                'latitude' => 'nullable|numeric',
                'longitude'=> 'nullable|numeric',
            ]);
            $u = $r->user();
            $id = (string) Str::orderedUuid();
            DB::table('properties')->insert([
                'id'=>$id,
                'company_id'=>$u->company_id,
                'code'=>$r->code,
                'title'=>$r->title,
                'description'=>$r->description,
                'type'=>$r->type,
                'address'=>$r->address,
                'latitude'=>$r->latitude,
                'longitude'=>$r->longitude,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id], 'Property dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat property', 500, 'SERVER_ERROR'); }
    }

    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.property:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('properties')->where('company_id',$u->company_id)->where('id',$id)->first();
            if (!$data) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Property');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat property', 500, 'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.property:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'code' => 'nullable|string|max:60',
                'title'=> 'nullable|string|max:150',
                'description'=> 'nullable|string',
                'type' => 'nullable|in:house,apartment,shop,land,warehouse,other',
                'address' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude'=> 'nullable|numeric',
            ]);
            $u = $r->user();
            $exists = DB::table('properties')->where('company_id',$u->company_id)->where('id',$id)->exists();
            if (!$exists) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('code','title','description','type','address','latitude','longitude'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('properties')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id], 'Property diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui property', 500, 'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.property:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $count = DB::table('properties')->where('company_id',$u->company_id)->where('id',$id)->delete();
            if (!$count) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Property dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus property', 500, 'SERVER_ERROR'); }
    }
}
