<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ModulesController extends Controller
{
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('modules:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $data=DB::table('modules')->orderBy('sort_order')->get();
            return $this->ok($data,'Modules');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat modules',500,'SERVER_ERROR'); }
    }
    public function store(Request $r)
    {
        try { if(!$r->user()->hasPermission('modules:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['key'=>'required|string|max:80','name'=>'required|string|max:120','is_active'=>'required|boolean','sort_order'=>'required|integer']);
            $id=(string)Str::orderedUuid();
            DB::table('modules')->insert(['id'=>$id,'key'=>$r->key,'name'=>$r->name,'is_active'=>$r->is_active,'sort_order'=>$r->sort_order,'created_at'=>now(),'updated_at'=>now()]);
            return $this->ok(['id'=>$id],'Module dibuat',[],201);
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal membuat module',500,'SERVER_ERROR'); }
    }
    public function update(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('modules:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['key'=>'nullable|string|max:80','name'=>'nullable|string|max:120','is_active'=>'nullable|boolean','sort_order'=>'nullable|integer']);
            $exists=DB::table('modules')->where('id',$id)->exists(); if(!$exists) return $this->fail('Module tidak ditemukan',404,'NOT_FOUND');
            $upd=array_filter($r->only('key','name','is_active','sort_order'), fn($v)=>!is_null($v)); $upd['updated_at']=now();
            DB::table('modules')->where('id',$id)->update($upd); return $this->ok(['id'=>$id],'Module diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui module',500,'SERVER_ERROR'); }
    }
    public function destroy(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('modules:delete')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $count=DB::table('modules')->where('id',$id)->delete(); if(!$count) return $this->fail('Module tidak ditemukan',404,'NOT_FOUND');
            return $this->ok(null,'Module dihapus');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal menghapus module',500,'SERVER_ERROR'); }
    }
}
