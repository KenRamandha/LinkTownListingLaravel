<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesUnitsController extends Controller
{
    public function indexByProperty(Request $r, string $propertyId)
    {
        try {
            if (!$r->user()->hasPermission('sales.property.unit:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $prop = DB::table('properties')->where('company_id',$u->company_id)->where('id',$propertyId)->exists();
            if (!$prop) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            $data = DB::table('property_units')->where('property_id',$propertyId)->orderBy('unit_code')->get();
            return $this->ok($data, 'Units');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat units', 500, 'SERVER_ERROR'); }
    }

    public function storeForProperty(Request $r, string $propertyId)
    {
        try {
            if (!$r->user()->hasPermission('sales.property.unit:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $prop = DB::table('properties')->where('company_id',$u->company_id)->where('id',$propertyId)->exists();
            if (!$prop) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            $r->validate([
                'unit_code' => 'required|string|max:60',
                'floor' => 'nullable|string|max:20',
                'size_m2' => 'nullable|numeric',
                'bedrooms' => 'nullable|integer',
                'bathrooms' => 'nullable|integer',
            ]);
            $id = (string) Str::orderedUuid();
            DB::table('property_units')->insert([
                'id'=>$id,
                'property_id'=>$propertyId,
                'unit_code'=>$r->unit_code,
                'floor'=>$r->floor,
                'size_m2'=>$r->size_m2,
                'bedrooms'=>$r->bedrooms,
                'bathrooms'=>$r->bathrooms,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id], 'Unit dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat unit', 500, 'SERVER_ERROR'); }
    }

    public function update(Request $r, string $unitId)
    {
        try {
            if (!$r->user()->hasPermission('sales.property.unit:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'unit_code' => 'nullable|string|max:60',
                'floor' => 'nullable|string|max:20',
                'size_m2' => 'nullable|numeric',
                'bedrooms' => 'nullable|integer',
                'bathrooms' => 'nullable|integer',
            ]);
            $unit = DB::table('property_units')->where('id',$unitId)->first();
            if (!$unit) return $this->fail('Unit tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('unit_code','floor','size_m2','bedrooms','bathrooms'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('property_units')->where('id',$unitId)->update($upd);
            return $this->ok(['id'=>$unitId], 'Unit diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui unit', 500, 'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $unitId)
    {
        try {
            if (!$r->user()->hasPermission('sales.property.unit:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $count = DB::table('property_units')->where('id',$unitId)->delete();
            if (!$count) return $this->fail('Unit tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Unit dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus unit', 500, 'SERVER_ERROR'); }
    }
}
