<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FeaturesController extends Controller
{
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('features:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $q=DB::table('features'); if($mid=$r->query('module_id')) $q->where('module_id',$mid);
            $data=$q->orderBy('name')->get(); return $this->ok($data,'Features');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat features',500,'SERVER_ERROR'); }
    }
    public function store(Request $r)
    {
        try { if(!$r->user()->hasPermission('features:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['module_id'=>'required|string','key'=>'required|string|max:120','name'=>'required|string|max:150','description'=>'nullable|string']);
            $id=(string)Str::orderedUuid(); DB::table('features')->insert(['id'=>$id,'module_id'=>$r->module_id,'key'=>$r->key,'name'=>$r->name,'description'=>$r->description,'created_at'=>now(),'updated_at'=>now()]);
            return $this->ok(['id'=>$id],'Feature dibuat',[],201);
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal membuat feature',500,'SERVER_ERROR'); }
    }
    public function update(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('features:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['module_id'=>'nullable|string','key'=>'nullable|string|max:120','name'=>'nullable|string|max:150','description'=>'nullable|string']);
            $exists=DB::table('features')->where('id',$id)->exists(); if(!$exists) return $this->fail('Feature tidak ditemukan',404,'NOT_FOUND');
            $upd=array_filter($r->only('module_id','key','name','description'), fn($v)=>!is_null($v)); $upd['updated_at']=now();
            DB::table('features')->where('id',$id)->update($upd); return $this->ok(['id'=>$id],'Feature diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui feature',500,'SERVER_ERROR'); }
    }
    public function destroy(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('features:delete')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $count=DB::table('features')->where('id',$id)->delete(); if(!$count) return $this->fail('Feature tidak ditemukan',404,'NOT_FOUND');
            return $this->ok(null,'Feature dihapus');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal menghapus feature',500,'SERVER_ERROR'); }
    }
}
