<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class UsersController extends Controller
{
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $q=DB::table('users'); if($cid=$r->query('company_id')) $q->where('company_id',$cid);
            if($s=$r->query('q')) $q->where(function($qq) use ($s){ $qq->where('name','like',"%$s%")->orWhere('email','like',"%$s%"); });
            $data=$q->orderBy('name')->limit((int)$r->query('limit',50))->get(); return $this->ok($data,'Users');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat users',500,'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try { if(!$r->user()->hasPermission('users:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'company_id'=>'required|string','name'=>'required|string|max:120','email'=>'required|email','password'=>'required|string|min:6',
                'is_employee'=>'required|boolean','status'=>'required|in:active,suspended,archived'
            ]);
            $id=(string)Str::orderedUuid();
            DB::table('users')->insert([
                'id'=>$id,'company_id'=>$r->company_id,'name'=>$r->name,'email'=>$r->email,
                'password'=>Hash::make($r->password),'is_employee'=>$r->is_employee,'status'=>$r->status,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id],'User dibuat',[],201);
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal membuat user',500,'SERVER_ERROR'); }
    }

    public function show(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $data=DB::table('users')->where('id',$id)->first(); if(!$data) return $this->fail('User tidak ditemukan',404,'NOT_FOUND');
            return $this->ok($data,'User');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat user',500,'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'company_id'=>'nullable|string','name'=>'nullable|string|max:120','email'=>'nullable|email',
                'password'=>'nullable|string|min:6','is_employee'=>'nullable|boolean','status'=>'nullable|in:active,suspended,archived'
            ]);
            $exists=DB::table('users')->where('id',$id)->exists(); if(!$exists) return $this->fail('User tidak ditemukan',404,'NOT_FOUND');
            $upd=array_filter($r->only('company_id','name','email','is_employee','status'), fn($v)=>!is_null($v));
            if($r->filled('password')) $upd['password']=Hash::make($r->password);
            $upd['updated_at']=now(); DB::table('users')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id],'User diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui user',500,'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:delete')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $count=DB::table('users')->where('id',$id)->delete(); if(!$count) return $this->fail('User tidak ditemukan',404,'NOT_FOUND');
            return $this->ok(null,'User dihapus');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal menghapus user',500,'SERVER_ERROR'); }
    }

    public function roles(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $roles=DB::table('user_roles')->join('roles','roles.id','=','user_roles.role_id')->where('user_roles.user_id',$id)->select('roles.id','roles.key','roles.name')->get();
            return $this->ok($roles,'User roles');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat user roles',500,'SERVER_ERROR'); }
    }

    public function setRoles(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['role_ids'=>'required|array|min:1','role_ids.*'=>'string']);
            DB::transaction(function() use ($id,$r){
                DB::table('user_roles')->where('user_id',$id)->delete();
                $rows=[]; foreach($r->role_ids as $rid){ $rows[]=['user_id'=>$id,'role_id'=>$rid,'created_at'=>now(),'updated_at'=>now()]; }
                if($rows) DB::table('user_roles')->insert($rows);
            });
            return $this->ok(null,'User roles diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui user roles',500,'SERVER_ERROR'); }
    }

    public function permissions(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $rolePerms = DB::table('role_permissions')
                ->join('permissions','permissions.id','=','role_permissions.permission_id')
                ->whereIn('role_id', DB::table('user_roles')->where('user_id',$id)->pluck('role_id'))
                ->where('allow', true)
                ->pluck('permissions.key');
            $userOverrides = DB::table('user_permissions')
                ->join('permissions','permissions.id','=','user_permissions.permission_id')
                ->where('user_id', $id)
                ->select('permissions.key','user_permissions.allow')->get();
            $eff = collect($rolePerms)->flip()->map(fn()=>true)->toArray();
            foreach ($userOverrides as $ov) { $eff[$ov->key] = (bool)$ov->allow; }
            return $this->ok(['permissions'=>array_keys(array_filter($eff))],'Effective permissions');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat permissions',500,'SERVER_ERROR'); }
    }

    public function setPermissions(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['overrides'=>'required|array|min:1','overrides.*.permission_id'=>'required|string','overrides.*.allow'=>'required|boolean']);
            DB::transaction(function() use ($r,$id){
                foreach($r->overrides as $ov){
                    $exists=DB::table('user_permissions')->where('user_id',$id)->where('permission_id',$ov['permission_id'])->exists();
                    if($exists){ DB::table('user_permissions')->where('user_id',$id)->where('permission_id',$ov['permission_id'])->update(['allow'=>$ov['allow'],'updated_at'=>now()]); }
                    else { DB::table('user_permissions')->insert(['user_id'=>$id,'permission_id'=>$ov['permission_id'],'allow'=>$ov['allow'],'created_at'=>now(),'updated_at'=>now()]); }
                }
            });
            return $this->ok(null,'User permission overrides diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal mengatur user permissions',500,'SERVER_ERROR'); }
    }

    public function profile(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users.view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $data=DB::table('user_profiles')->where('user_id',$id)->first();
            return $this->ok($data ?: (object)[],'User profile');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat user profile',500,'SERVER_ERROR'); }
    }

    public function updateProfile(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('users.update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'employee_code'=>'nullable|string|max:50','phone'=>'nullable|string|max:50','avatar_url'=>'nullable|string',
                'department_id'=>'nullable|string','position'=>'nullable|string|max:100','join_date'=>'nullable|date','resign_date'=>'nullable|date'
            ]);
            $exists=DB::table('user_profiles')->where('user_id',$id)->exists();
            $payload=array_merge(['user_id'=>$id], $r->only('employee_code','phone','avatar_url','department_id','position','join_date','resign_date'));
            if($exists){ $payload['updated_at']=now(); DB::table('user_profiles')->where('user_id',$id)->update($payload); }
            else { $payload['id']=(string)Str::orderedUuid(); $payload['created_at']=now(); $payload['updated_at']=now(); DB::table('user_profiles')->insert($payload); }
            return $this->ok(null,'User profile diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui user profile',500,'SERVER_ERROR'); }
    }
}
