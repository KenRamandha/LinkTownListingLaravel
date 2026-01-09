<?php


namespace App\Http\Controllers\Attendance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AttendanceShiftsController extends Controller
{
    // GET /api/attendance/shifts - Ambil daftar shift
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $query = DB::table('shifts')->orderBy('name');
            if ($this->shiftHasCompanyColumn()) {
                $query->where('company_id', $r->user()->company_id);
            }
            $data = $query->get();
            return $this->ok($data, 'Shifts');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat shifts', 500, 'SERVER_ERROR');
        }
    }


    // POST /api/attendance/shifts - Buat shift baru
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'name' => 'required|string|max:60',
                'start_time' => 'required|date_format:H:i:s',
                'end_time' => 'required|date_format:H:i:s',
                'timezone' => 'required|string|max:64',
            ]);
            $id = (string) Str::orderedUuid();
            $payload = [
                'id' => $id,
                'name' => $r->name,
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'timezone' => $r->timezone,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($this->shiftHasCompanyColumn()) {
                $payload['company_id'] = $r->user()->company_id;
            }
            DB::table('shifts')->insert($payload);
            return $this->ok(['id' => $id], 'Shift dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat shift', 500, 'SERVER_ERROR');
        }
    }


    // PUT /api/attendance/shifts/{id} - Update shift
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'name' => 'nullable|string|max:60',
                'start_time' => 'nullable|date_format:H:i:s',
                'end_time' => 'nullable|date_format:H:i:s',
                'timezone' => 'nullable|string|max:64',
            ]);
            $query = DB::table('shifts')->where('id', $id);
            if ($this->shiftHasCompanyColumn()) {
                $query->where('company_id', $r->user()->company_id);
            }
            $record = $query->first();
            if (!$record) return $this->fail('Shift tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('name', 'start_time', 'end_time', 'timezone'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            $query->update($upd);
            return $this->ok(['id' => $id], 'Shift diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui shift', 500, 'SERVER_ERROR');
        }
    }


    // DELETE /api/attendance/shifts/{id} - Hapus shift
    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.shift:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $query = DB::table('shifts')->where('id', $id);
            if ($this->shiftHasCompanyColumn()) {
                $query->where('company_id', $r->user()->company_id);
            }
            $record = $query->first();
            if (!$record) return $this->fail('Shift tidak ditemukan', 404, 'NOT_FOUND');
            $query->delete();
            return $this->ok(null, 'Shift dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus shift', 500, 'SERVER_ERROR');
        }
    }

    private function shiftHasCompanyColumn(): bool
    {
        static $has = null;
        if (is_null($has)) {
            $has = Schema::hasColumn('shifts', 'company_id');
        }
        return $has;
    }
}
