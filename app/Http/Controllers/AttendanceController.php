<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AttendanceController extends Controller
{
    public function allowedLocations(Request $r)
    {
        try {
            $r->validate([
                'lat' => 'nullable|numeric',
                'lng' => 'nullable|numeric',
            ]);
            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $list = DB::table('geofences')
                ->where('company_id', $u->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'latitude', 'longitude', 'radius_m']);

            $lat = $r->query('lat');
            $lng = $r->query('lng');
            $anyAllowed = null;

            if (!is_null($lat) && !is_null($lng)) {
                $lat = (float) $lat;
                $lng = (float) $lng;
                $list = $list->map(function ($g) use ($lat, $lng, &$anyAllowed) {
                    $d = $this->distanceMeters($lat, $lng, (float)$g->latitude, (float)$g->longitude);
                    $allowed = $d <= (float)$g->radius_m;
                    $g->distance_m = (int) round($d);
                    $g->allowed = $allowed;
                    $anyAllowed = is_null($anyAllowed) ? $allowed : ($anyAllowed || $allowed);
                    return $g;
                })->sortBy('distance_m')->values();
            }

            return $this->ok([
                'locations' => $list,
                'any_allowed' => $anyAllowed,
            ], 'Allowed locations');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat lokasi yang diizinkan', 500, 'SERVER_ERROR');
        }
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) return null;
        $path = ltrim($path, '/');
        // Ensure absolute URL for mobile apps
        return asset('storage/' . $path);
    }

    public function clock(Request $r)
    {
        try {
            $r->validate([
                'type'        => 'required|in:clock_in,clock_out,break_out,break_in',
                'latitude'    => 'nullable|numeric',
                'longitude'   => 'nullable|numeric',
                'photo_url'   => 'nullable|file|image|max:5120',
                'video_url'   => 'nullable|string',
                'device_info' => 'nullable|string',
                'geofence_id' => 'nullable|string',
                'note'       => 'nullable|string',
            ]);

            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $now = now('Asia/Jakarta');

            $photoPath = null;
            if ($r->hasFile('photo_url') && $r->file('photo_url')->isValid()) {
                $file = $r->file('photo_url');
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'photo_' . $u->id . '_' . $now->format('Ymd_His') . '_' . Str::random(6) . '.' . strtolower($ext);
                $photoPath = Storage::disk('public')->putFileAs('attendance', $file, $filename);
            }

            $payload = $r->only(['type', 'latitude', 'longitude', 'video_url', 'device_info', 'geofence_id', 'note']);
            if (array_key_exists('note', $payload)) {
                $payload['note'] = $r->filled('note') ? trim($payload['note']) : null;
            }

            $data = array_merge($payload, [
                'id' => (string) Str::orderedUuid(),
                'user_id' => $u->id,
                'work_date' => $now->toDateString(),
                'logged_at' => $now->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
                'photo_url' => $photoPath,
            ]);
            DB::table('attendance_logs')->insert($data);
            $resp = $data;
            $resp['photo_url'] = $this->publicUrl($data['photo_url']);
            return $this->ok($resp, 'Clock berhasil', [], 201);
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
                ->where('user_id', $u->id)
                ->where('work_date', $date)
                ->orderBy('logged_at')
                ->get()
                ->map(function ($row) {
                    // expose absolute URL for photo_url
                    $row->photo_url = $this->publicUrl($row->photo_url ?? null);
                    return $row;
                });

            return $this->ok(['date' => $date, 'logs' => $logs], 'Logs loaded');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat logs', 500, 'SERVER_ERROR');
        }
    }

    /**
     * GET /api/attendance/overview
     * Optional query: date=YYYY-MM-DD, lat, lng
     * Returns: company, date, shift (today), locations (geofences), logs (clock_in/out)
     */
    public function overview(Request $r)
    {
        try {
            $r->validate([
                'date' => 'nullable|date',
                'lat'  => 'nullable|numeric',
                'lng'  => 'nullable|numeric',
            ]);

            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $date = $r->query('date', now('Asia/Jakarta')->toDateString());

            // Company info (safe if table not present)
            $companyName = null;
            if (Schema::hasTable('companies')) {
                try {
                    $companyName = DB::table('companies')->where('id', $u->company_id)->value('name');
                } catch (Throwable $e) {
                    // ignore if table/column shape differs
                    $companyName = null;
                }
            }

            // Locations (geofences)
            $locations = DB::table('geofences')
                ->where('company_id', $u->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'latitude', 'longitude', 'radius_m']);

            $lat = $r->query('lat');
            $lng = $r->query('lng');
            $anyAllowed = null;
            if (!is_null($lat) && !is_null($lng)) {
                $lat = (float) $lat;
                $lng = (float) $lng;
                $locations = $locations->map(function ($g) use ($lat, $lng, &$anyAllowed) {
                    $d = $this->distanceMeters($lat, $lng, (float)$g->latitude, (float)$g->longitude);
                    $allowed = $d <= (float)$g->radius_m;
                    $g->distance_m = (int) round($d);
                    $g->allowed = $allowed;
                    $anyAllowed = is_null($anyAllowed) ? $allowed : ($anyAllowed || $allowed);
                    return $g;
                })->sortBy('distance_m')->values();
            }

            // Shift (from work_schedules + work_shifts)
            $schedule = DB::table('work_schedules')
                ->where('user_id', $u->id)
                ->where('work_date', $date)
                ->first();

            $shift = null;
            if ($schedule && $schedule->shift_id) {
                $s = DB::table('work_shifts')->where('id', $schedule->shift_id)->first();
                if ($s) {
                    $shift = [
                        'id' => $s->id,
                        'name' => $s->name,
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                        'break_minutes' => (int) $s->break_minutes,
                        'grace_minutes' => (int) $s->grace_minutes,
                        'is_holiday' => (bool) $schedule->is_holiday,
                    ];
                }
            }

            // Logs (clock_in & clock_out only)
            $logs = DB::table('attendance_logs')
                ->where('user_id', $u->id)
                ->where('work_date', $date)
                ->whereIn('type', ['clock_in', 'clock_out'])
                ->orderBy('logged_at')
                ->get()
                ->map(function ($row) {
                    $row->photo_url = $this->publicUrl($row->photo_url ?? null);
                    return $row;
                });

            return $this->ok([
                'company' => [
                    'id' => $u->company_id,
                    'name' => $companyName,
                ],
                'date' => $date,
                'shift' => $shift,
                'locations' => $locations,
                'any_allowed' => $anyAllowed,
                'logs' => $logs,
            ], 'Attendance overview');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat ringkasan absensi', 500, 'SERVER_ERROR');
        }
    }
}
