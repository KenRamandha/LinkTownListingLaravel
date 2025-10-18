<?php


namespace App\Http\Controllers\Attendance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AttendanceGeofencesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.geofence:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('geofences')->where('company_id', $u->company_id)->orderBy('name')->get();
            return $this->ok($data, 'Geofences');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat geofences', 500, 'SERVER_ERROR');
        }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.geofence:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'name' => 'required|string|max:120',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'radius_m' => 'required|integer|min:1'
            ]);
            $u = $r->user();
            $id = (string) Str::orderedUuid();
            DB::table('geofences')->insert([
                'id' => $id,
                'company_id' => $u->company_id,
                'name' => $r->name,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'radius_m' => $r->radius_m,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Geofence dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat geofence', 500, 'SERVER_ERROR');
        }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.geofence:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'name' => 'nullable|string|max:120',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'radius_m' => 'nullable|integer|min:1'
            ]);
            $u = $r->user();
            $exists = DB::table('geofences')->where('company_id', $u->company_id)->where('id', $id)->exists();
            if (!$exists) return $this->fail('Geofence tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('name', 'latitude', 'longitude', 'radius_m'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('geofences')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Geofence diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui geofence', 500, 'SERVER_ERROR');
        }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.geofence:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $count = DB::table('geofences')->where('company_id', $u->company_id)->where('id', $id)->delete();
            if (!$count) return $this->fail('Geofence tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Geofence dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus geofence', 500, 'SERVER_ERROR');
        }
    }
}
