<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesListingsController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.listing:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $q = DB::table('listings')
                ->join('properties','properties.id','=','listings.property_id')
                ->where('properties.company_id',$u->company_id);
            if ($t = $r->query('type')) $q->where('listings.listing_type',$t);
            if ($s = $r->query('status')) $q->where('listings.status',$s);
            $data = $q->select('listings.*','properties.title as property_title')
                ->orderByDesc('listings.updated_at')
                ->limit((int)$r->query('limit',50))
                ->get();
            return $this->ok($data, 'Listings');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat listings', 500, 'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('sales.listing:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'property_id' => 'required|string',
                'unit_id' => 'nullable|string',
                'listing_type' => 'required|in:sale,rent',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|string|max:10',
                'status' => "required|in:draft,published,archived",
            ]);
            $u = $r->user();
            $prop = DB::table('properties')->where('company_id',$u->company_id)->where('id',$r->property_id)->exists();
            if (!$prop) return $this->fail('Property tidak ditemukan', 404, 'NOT_FOUND');
            $id = (string) Str::orderedUuid();
            DB::table('listings')->insert([
                'id'=>$id,
                'property_id'=>$r->property_id,
                'unit_id'=>$r->unit_id,
                'listing_type'=>$r->listing_type,
                'price'=>$r->price,
                'currency'=>$r->currency,
                'status'=>$r->status,
                'listed_by'=>$u->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id], 'Listing dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat listing', 500, 'SERVER_ERROR'); }
    }

    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.listing:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('listings')
                ->join('properties','properties.id','=','listings.property_id')
                ->where('properties.company_id',$u->company_id)
                ->where('listings.id',$id)
                ->select('listings.*','properties.title as property_title')
                ->first();
            if (!$data) return $this->fail('Listing tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Listing');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat listing', 500, 'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.listing:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'unit_id' => 'nullable|string',
                'listing_type' => 'nullable|in:sale,rent',
                'price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:10',
                'status' => "nullable|in:draft,published,archived",
            ]);
            $u = $r->user();
            $exists = DB::table('listings')
                ->join('properties','properties.id','=','listings.property_id')
                ->where('properties.company_id',$u->company_id)
                ->where('listings.id',$id)->exists();
            if (!$exists) return $this->fail('Listing tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('unit_id','listing_type','price','currency','status'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('listings')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id], 'Listing diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui listing', 500, 'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('sales.listing:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $exists = DB::table('listings')
                ->join('properties','properties.id','=','listings.property_id')
                ->where('properties.company_id',$u->company_id)
                ->where('listings.id',$id)->exists();
            if (!$exists) return $this->fail('Listing tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('listings')->where('id',$id)->delete();
            return $this->ok(null, 'Listing dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus listing', 500, 'SERVER_ERROR'); }
    }
}
