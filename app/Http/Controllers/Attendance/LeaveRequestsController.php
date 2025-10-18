<?php


namespace App\Http\Controllers\Attendance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LeaveRequestsController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $q = DB::table('leave_requests');
            if ($uid = $r->query('user_id')) $q->where('user_id', $uid);
            if ($st = $r->query('status')) $q->where('status', $st);
            $data = $q->orderByDesc('created_at')->limit((int)$r->query('limit', 50))->get();
            return $this->ok($data, 'Leave requests');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat leave requests', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'user_id' => 'required|string',
                'leave_type_id' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);
            $id = (string)Str::orderedUuid();
            DB::table('leave_requests')->insert([
                'id' => $id,
                'user_id' => $r->user_id,
                'leave_type_id' => $r->leave_type_id,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'reason' => $r->reason,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Leave request dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat leave request', 500, 'SERVER_ERROR');
        }
    }
    public function show(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('leave_requests')->where('id', $id)->first();
            if (!$data) return $this->fail('Leave request tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok($data, 'Leave request');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat leave request', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate(['reason' => 'nullable|string']);
            $exists = DB::table('leave_requests')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Leave request tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('leave_requests')->where('id', $id)->update(['reason' => $r->reason, 'updated_at' => now()]);
            return $this->ok(['id' => $id], 'Leave request diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui leave request', 500, 'SERVER_ERROR');
        }
    }
    public function approve(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:approve')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $exists = DB::table('leave_requests')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Leave request tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('leave_requests')->where('id', $id)->update(['status' => 'approved', 'approved_at' => now(), 'approver_id' => $r->user()->id, 'updated_at' => now()]);
            return $this->ok(['id' => $id, 'status' => 'approved'], 'Leave request disetujui');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menyetujui leave request', 500, 'SERVER_ERROR');
        }
    }
    public function reject(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave:approve')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $exists = DB::table('leave_requests')->where('id', $id)->exists();
            if (!$exists) return $this->fail('Leave request tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('leave_requests')->where('id', $id)->update(['status' => 'rejected', 'approved_at' => now(), 'approver_id' => $r->user()->id, 'updated_at' => now()]);
            return $this->ok(['id' => $id, 'status' => 'rejected'], 'Leave request ditolak');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menolak leave request', 500, 'SERVER_ERROR');
        }
    }
}
