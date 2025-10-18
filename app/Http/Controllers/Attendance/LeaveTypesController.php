<?php


namespace App\Http\Controllers\Attendance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LeaveTypesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave_type:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('leave_types')->where('company_id', $u->company_id)->orderBy('name')->get();
            return $this->ok($data, 'Leave types');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat leave types', 500, 'SERVER_ERROR');
        }
    }
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave_type:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'code' => 'required|string|max:30',
                'name' => 'required|string|max:100',
                'requires_approval' => 'required|boolean',
                'quota_days' => 'required|numeric',
            ]);
            $u = $r->user();
            $id = (string)Str::orderedUuid();
            DB::table('leave_types')->insert([
                'id' => $id,
                'company_id' => $u->company_id,
                'code' => $r->code,
                'name' => $r->name,
                'requires_approval' => $r->requires_approval,
                'quota_days' => $r->quota_days,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Leave type dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat leave type', 500, 'SERVER_ERROR');
        }
    }
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave_type:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'code' => 'nullable|string|max:30',
                'name' => 'nullable|string|max:100',
                'requires_approval' => 'nullable|boolean',
                'quota_days' => 'nullable|numeric',
            ]);
            $u = $r->user();
            $exists = DB::table('leave_types')->where('company_id', $u->company_id)->where('id', $id)->exists();
            if (!$exists) return $this->fail('Leave type tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('code', 'name', 'requires_approval', 'quota_days'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('leave_types')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Leave type diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui leave type', 500, 'SERVER_ERROR');
        }
    }
    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.leave_type:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $count = DB::table('leave_types')->where('company_id', $u->company_id)->where('id', $id)->delete();
            if (!$count) return $this->fail('Leave type tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Leave type dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus leave type', 500, 'SERVER_ERROR');
        }
    }
}
