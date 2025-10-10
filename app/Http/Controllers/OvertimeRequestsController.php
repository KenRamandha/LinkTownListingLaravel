<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OvertimeRequestsController extends Controller
{
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $q=DB::table('overtime_requests');
            if($uid=$r->query('user_id')) $q->where('user_id',$uid);
            if($st=$r->query('status')) $q->where('status',$st);
            $data=$q->orderByDesc('work_date')->limit((int)$r->query('limit',50))->get();
            return $this->ok($data,'Overtime requests');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat overtime requests',500,'SERVER_ERROR'); }
    }
    public function store(Request $r)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'user_id'=>'required|string',
                'work_date'=>'required|date',
                'start_time'=>'required|date_format:H:i:s',
                'end_time'=>'required|date_format:H:i:s',
                'reason'=>'nullable|string',
            ]);
            $id=(string)Str::orderedUuid();
            DB::table('overtime_requests')->insert([
                'id'=>$id,'user_id'=>$r->user_id,'work_date'=>$r->work_date,
                'start_time'=>$r->start_time,'end_time'=>$r->end_time,'reason'=>$r->reason,
                'status'=>'pending','created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id],'Overtime request dibuat',[],201);
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal membuat overtime request',500,'SERVER_ERROR'); }
    }
    public function show(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $data=DB::table('overtime_requests')->where('id',$id)->first();
            if(!$data) return $this->fail('Overtime request tidak ditemukan',404,'NOT_FOUND');
            return $this->ok($data,'Overtime request');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat overtime request',500,'SERVER_ERROR'); }
    }
    public function update(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate(['reason'=>'nullable|string']);
            $exists=DB::table('overtime_requests')->where('id',$id)->exists();
            if(!$exists) return $this->fail('Overtime request tidak ditemukan',404,'NOT_FOUND');
            DB::table('overtime_requests')->where('id',$id)->update(['reason'=>$r->reason,'updated_at'=>now()]);
            return $this->ok(['id'=>$id],'Overtime request diperbarui');
        } catch(\Illuminate\Validation\ValidationException $e){ throw $e; }
        catch(Throwable $e){ report($e); return $this->fail('Gagal memperbarui overtime request',500,'SERVER_ERROR'); }
    }
    public function approve(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:approve')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $exists=DB::table('overtime_requests')->where('id',$id)->exists();
            if(!$exists) return $this->fail('Overtime request tidak ditemukan',404,'NOT_FOUND');
            DB::table('overtime_requests')->where('id',$id)->update(['status'=>'approved','approved_at'=>now(),'approver_id'=>$r->user()->id,'updated_at'=>now()]);
            return $this->ok(['id'=>$id,'status'=>'approved'],'Overtime request disetujui');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal menyetujui overtime request',500,'SERVER_ERROR'); }
    }
    public function reject(Request $r, string $id)
    {
        try { if(!$r->user()->hasPermission('attendance.overtime:approve')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $exists=DB::table('overtime_requests')->where('id',$id)->exists();
            if(!$exists) return $this->fail('Overtime request tidak ditemukan',404,'NOT_FOUND');
            DB::table('overtime_requests')->where('id',$id)->update(['status'=>'rejected','approved_at'=>now(),'approver_id'=>$r->user()->id,'updated_at'=>now()]);
            return $this->ok(['id'=>$id,'status'=>'rejected'],'Overtime request ditolak');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal menolak overtime request',500,'SERVER_ERROR'); }
    }
}
