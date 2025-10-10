<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AttendanceShiftsController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('work_shifts')->where('company_id',$u->company_id)->orderBy('name')->get();
            return $this->ok($data,'Shifts');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal memuat shifts',500,'SERVER_ERROR'); }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:create')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'name'=>'required|string|max:60',
                'start_time'=>'required|date_format:H:i:s',
                'end_time'=>'required|date_format:H:i:s',
                'break_minutes'=>'required|integer|min:0',
                'grace_minutes'=>'required|integer|min:0',
            ]);
            $u = $r->user();
            $id = (string) Str::orderedUuid();
            DB::table('work_shifts')->insert([
                'id'=>$id,'company_id'=>$u->company_id,'name'=>$r->name,
                'start_time'=>$r->start_time,'end_time'=>$r->end_time,
                'break_minutes'=>$r->break_minutes,'grace_minutes'=>$r->grace_minutes,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $this->ok(['id'=>$id],'Shift dibuat',[],201);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal membuat shift',500,'SERVER_ERROR'); }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:update')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $r->validate([
                'name'=>'nullable|string|max:60',
                'start_time'=>'nullable|date_format:H:i:s',
                'end_time'=>'nullable|date_format:H:i:s',
                'break_minutes'=>'nullable|integer|min:0',
                'grace_minutes'=>'nullable|integer|min:0',
            ]);
            $u = $r->user();
            $exists = DB::table('work_shifts')->where('company_id',$u->company_id)->where('id',$id)->exists();
            if (!$exists) return $this->fail('Shift tidak ditemukan',404,'NOT_FOUND');
            $upd = array_filter($r->only('name','start_time','end_time','break_minutes','grace_minutes'), fn($v)=>!is_null($v));
            $upd['updated_at'] = now();
            DB::table('work_shifts')->where('id',$id)->update($upd);
            return $this->ok(['id'=>$id],'Shift diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (Throwable $e) { report($e); return $this->fail('Gagal memperbarui shift',500,'SERVER_ERROR'); }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:delete')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $u = $r->user();
            $count = DB::table('work_shifts')->where('company_id',$u->company_id)->where('id',$id)->delete();
            if (!$count) return $this->fail('Shift tidak ditemukan',404,'NOT_FOUND');
            return $this->ok(null,'Shift dihapus');
        } catch (Throwable $e) { report($e); return $this->fail('Gagal menghapus shift',500,'SERVER_ERROR'); }
    }
}
