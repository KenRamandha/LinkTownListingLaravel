<?php


namespace App\Http\Controllers\Attendance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AttendanceSchedulesController extends Controller
{
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->user()->company_id;
            $q = DB::table('shifts_mapping')
                ->join('users', 'users.id', '=', 'shifts_mapping.user_id')
                ->where('users.company_id', $companyId)
                ->select('shifts_mapping.*');

            if ($uid = $r->query('user_id')) {
                $q->where('shifts_mapping.user_id', $uid);
            }
            if ($date = $r->query('date')) {
                $q->where('shifts_mapping.work_date', $date);
            }

            $limit = (int) $r->query('limit', 50);
            $data = $q->orderByDesc('shifts_mapping.work_date')->limit(min($limit, 200))->get();
            return $this->ok($data, 'Schedules');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat schedules', 500, 'SERVER_ERROR');
        }
    }

    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'user_id' => 'required|string',
                'shift_id' => 'required|string',
                'work_date' => 'required|date',
                'attendance_status' => 'nullable|string|max:40',
                'checkin_time' => 'nullable',
                'checkout_time' => 'nullable',
                'late' => 'nullable|integer|min:0',
                'early_checkout' => 'nullable|integer|min:0',
                'checkin_lat' => 'nullable|numeric',
                'checkin_lng' => 'nullable|numeric',
                'checkin_distance' => 'nullable|numeric',
                'checkin_photo' => 'nullable|string',
                'checkin_note' => 'nullable|string',
                'checkout_lat' => 'nullable|numeric',
                'checkout_lng' => 'nullable|numeric',
                'checkout_distance' => 'nullable|numeric',
                'checkout_photo' => 'nullable|string',
                'checkout_note' => 'nullable|string',
                'lock_location' => 'nullable|boolean',
                'proposed_checkin_time' => 'nullable',
                'proposed_checkout_time' => 'nullable',
                'description' => 'nullable|string',
                'request_status' => 'nullable|string|max:40',
                'request_file' => 'nullable|string',
                'comment' => 'nullable|string',
                'approved_by' => 'nullable|string',
            ]);
            $id = (string) Str::orderedUuid();
            $companyId = $r->user()->company_id;
            $user = DB::table('users')
                ->where('id', $r->user_id)
                ->where('company_id', $companyId)
                ->first();
            if (!$user) return $this->fail('User tidak ditemukan', 404, 'NOT_FOUND');
            $shiftQuery = DB::table('shifts')->where('id', $r->shift_id);
            if ($this->shiftHasCompanyColumn()) {
                $shiftQuery->where('company_id', $companyId);
            }
            $shift = $shiftQuery->first();
            if (!$shift) return $this->fail('Shift tidak ditemukan', 404, 'NOT_FOUND');

            $data = [
                'id' => $id,
                'user_id' => $r->user_id,
                'shift_id' => $r->shift_id,
                'work_date' => $r->work_date,
                'attendance_status' => $r->input('attendance_status', 'scheduled'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $optional = [
                'checkin_time',
                'late',
                'checkin_lat',
                'checkin_lng',
                'checkin_distance',
                'checkin_address',
                'checkin_photo',
                'checkin_note',
                'checkout_time',
                'early_checkout',
                'checkout_lat',
                'checkout_lng',
                'checkout_distance',
                'checkout_address',
                'checkout_photo',
                'checkout_note',
                'proposed_checkin_time',
                'proposed_checkout_time',
                'description',
                'request_status',
                'request_file',
                'comment',
                'approved_by',
            ];

            foreach ($optional as $field) {
                if ($r->has($field)) {
                    $data[$field] = $r->input($field);
                }
            }

            if ($r->has('lock_location')) {
                $data['lock_location'] = $r->boolean('lock_location');
            }

            foreach (['late', 'early_checkout'] as $intField) {
                if (array_key_exists($intField, $data) && !is_null($data[$intField])) {
                    $data[$intField] = (int) $data[$intField];
                }
            }

            foreach (['checkin_lat', 'checkin_lng', 'checkin_distance', 'checkout_lat', 'checkout_lng', 'checkout_distance'] as $floatField) {
                if (array_key_exists($floatField, $data) && !is_null($data[$floatField])) {
                    $data[$floatField] = (float) $data[$floatField];
                }
            }

            DB::table('shifts_mapping')->insert($data);
            return $this->ok(['id' => $id], 'Schedule dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat schedule', 500, 'SERVER_ERROR');
        }
    }

    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'shift_id' => 'nullable|string',
                'checkin_time' => 'nullable',
                'late' => 'nullable|integer|min:0',
                'checkin_lat' => 'nullable|numeric',
                'checkin_lng' => 'nullable|numeric',
                'checkin_distance' => 'nullable|numeric',
                'checkin_address' => 'nullable|string',
                'checkin_photo' => 'nullable|string',
                'checkin_note' => 'nullable|string',
                'checkout_time' => 'nullable',
                'early_checkout' => 'nullable|integer|min:0',
                'checkout_lat' => 'nullable|numeric',
                'checkout_lng' => 'nullable|numeric',
                'checkout_distance' => 'nullable|numeric',
                'checkout_address' => 'nullable|string',
                'checkout_photo' => 'nullable|string',
                'checkout_note' => 'nullable|string',
                'attendance_status' => 'nullable|string|max:40',
                'lock_location' => 'nullable|boolean',
                'proposed_checkin_time' => 'nullable',
                'proposed_checkout_time' => 'nullable',
                'description' => 'nullable|string',
                'request_status' => 'nullable|string|max:40',
                'request_file' => 'nullable|string',
                'approved_by' => 'nullable|string',
                'comment' => 'nullable|string',
            ]);
            $companyId = $r->user()->company_id;
            $mapping = DB::table('shifts_mapping')
                ->join('users', 'users.id', '=', 'shifts_mapping.user_id')
                ->where('shifts_mapping.id', $id)
                ->where('users.company_id', $companyId)
                ->select('shifts_mapping.user_id', 'shifts_mapping.shift_id')
                ->first();
            if (!$mapping) return $this->fail('Schedule tidak ditemukan', 404, 'NOT_FOUND');

            $fields = [
                'shift_id',
                'checkin_time',
                'late',
                'checkin_lat',
                'checkin_lng',
                'checkin_distance',
                'checkin_address',
                'checkin_photo',
                'checkin_note',
                'checkout_time',
                'early_checkout',
                'checkout_lat',
                'checkout_lng',
                'checkout_distance',
                'checkout_address',
                'checkout_photo',
                'checkout_note',
                'attendance_status',
                'proposed_checkin_time',
                'proposed_checkout_time',
                'description',
                'request_status',
                'request_file',
                'approved_by',
                'comment',
            ];

            $payload = [];
            foreach ($fields as $field) {
                if ($r->has($field)) {
                    $payload[$field] = $r->input($field);
                }
            }

            if ($r->has('lock_location')) {
                $payload['lock_location'] = $r->boolean('lock_location');
            }

            foreach (['late', 'early_checkout'] as $intField) {
                if (array_key_exists($intField, $payload) && !is_null($payload[$intField])) {
                    $payload[$intField] = (int) $payload[$intField];
                }
            }

            foreach (['checkin_lat', 'checkin_lng', 'checkin_distance', 'checkout_lat', 'checkout_lng', 'checkout_distance'] as $floatField) {
                if (array_key_exists($floatField, $payload) && !is_null($payload[$floatField])) {
                    $payload[$floatField] = (float) $payload[$floatField];
                }
            }

            if (array_key_exists('shift_id', $payload) && $payload['shift_id'] !== $mapping->shift_id) {
                $shiftQuery = DB::table('shifts')->where('id', $payload['shift_id']);
                if ($this->shiftHasCompanyColumn()) {
                    $shiftQuery->where('company_id', $companyId);
                }
                $shift = $shiftQuery->first();
                if (!$shift) return $this->fail('Shift tidak ditemukan', 404, 'NOT_FOUND');
            }

            $payload['updated_at'] = now();
            DB::table('shifts_mapping')->where('id', $id)->update($payload);
            return $this->ok(['id' => $id], 'Schedule diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui schedule', 500, 'SERVER_ERROR');
        }
    }

    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('attendance.schedule:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->user()->company_id;
            $exists = DB::table('shifts_mapping')
                ->join('users', 'users.id', '=', 'shifts_mapping.user_id')
                ->where('shifts_mapping.id', $id)
                ->where('users.company_id', $companyId)
                ->select('shifts_mapping.id')
                ->first();
            if (!$exists) return $this->fail('Schedule tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('shifts_mapping')->where('id', $id)->delete();
            return $this->ok(null, 'Schedule dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus schedule', 500, 'SERVER_ERROR');
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
