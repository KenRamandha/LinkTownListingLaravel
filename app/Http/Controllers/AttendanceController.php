<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AttendanceController extends Controller
{
    public function clock(Request $r)
    {
        try {
            $r->validate([
              'type'=>'required|in:clock_in,clock_out,break_out,break_in',
              'latitude'=>'nullable|numeric',
              'longitude'=>'nullable|numeric',
              'photo_url'=>'nullable|string',
              'video_url'=>'nullable|string',
              'device_info'=>'nullable|string',
              'geofence_id'=>'nullable|string'
            ]);

            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $now = now('Asia/Jakarta');
            $data = array_merge($r->only(['type','latitude','longitude','photo_url','video_url','device_info','geofence_id']),[
              'id'=> (string) Str::orderedUuid(),
              'user_id'=> $u->id,
              'work_date'=> $now->toDateString(),
              'logged_at'=> $now->toDateTimeString(),
              'created_at'=> $now,
              'updated_at'=> $now,
            ]);
            DB::table('attendance_logs')->insert($data);

            return $this->ok($data, 'Clock berhasil', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal clock', 500, 'SERVER_ERROR');
        }
    }

    public function logs(Request $r)
    {
        try {
            $u = $r->user();
            $date = $r->query('date', now('Asia/Jakarta')->toDateString());
            $logs = DB::table('attendance_logs')
                ->where('user_id',$u->id)
                ->where('work_date',$date)
                ->orderBy('logged_at')
                ->get();

            return $this->ok(['date'=>$date,'logs'=>$logs], 'Logs loaded');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat logs', 500, 'SERVER_ERROR');
        }
    }
}
