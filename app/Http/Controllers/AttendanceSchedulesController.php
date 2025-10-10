<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AttendanceSchedulesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $q = DB::table('work_schedules');
            if ($uid = $r->query('user_id')) $q->where('user_id',$uid);
            if ($date = $r->query('date')) $q->where('work_date',$date);
            $data = $q->orderByDesc('work_date')->limit((int)$r->query('limit',50))->get();
            return $this->ok($data,'Schedules');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat schedules',500,'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'user_id'=>'required|string',
                'work_date'=>'required|date',
                'shift_id'=>'required|string',
                'is_holiday'=>'required|boolean',
            ]);
            $id = (string) Str::orderedUuid();
            DB::table('work_schedules')->insert([
                'id'=>$id,
                'user_id'=>$r->user_id,
                'work_date'=>$r->work_date,
                'shift_id'=>$r->shift_id,
                'is_holiday'=>$r->is_holiday,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id],'Schedule dibuat',[],201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat schedule',500,'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'shift_id'=>'nullable|string',
                'is_holiday'=>'nullable|boolean',
            ]);
            $exists = DB::table('work_schedules')->where('id',$id)->exists();
            if (!$exists) return $this->fail('Schedule tidak ditemukan',404,'NOT_FOUND');
            $upd = array_filter($r->only('shift_id','is_holiday'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('work_schedules')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id],'Schedule diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui schedule',500,'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:delete')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $count = DB::table('work_schedules')->where('id',$id)->delete();
            if (!$count) return $this->fail('Schedule tidak ditemukan',404,'NOT_FOUND');
            return $this->ok(null,'Schedule dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus schedule',500,'SERVER_ERROR'); }
    }
}
