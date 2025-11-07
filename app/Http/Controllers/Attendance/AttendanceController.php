<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Models\Attendance\ShiftMapping;

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

            $geofenceRows = $this->loadGeofences($u->company_id);

            $list = collect($geofenceRows)->map(fn($row) => (object) $row);

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
        $earth = 6371000;
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
                'note'        => 'nullable|string',
                'address'     => 'nullable|string',
            ]);

            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $now = now('Asia/Jakarta');
            $workDate = $now->toDateString();

            $photoPath = null;
            if ($r->hasFile('photo_url') && $r->file('photo_url')->isValid()) {
                $file = $r->file('photo_url');
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'photo_' . $u->id . '_' . $now->format('Ymd_His') . '_' . Str::random(6) . '.' . strtolower($ext);
                $photoPath = Storage::disk('public')->putFileAs('attendance', $file, $filename);
            }

            $payload = $r->only(['type', 'latitude', 'longitude', 'video_url', 'device_info', 'geofence_id', 'note', 'address']);
            if (array_key_exists('note', $payload)) {
                $payload['note'] = $r->filled('note') ? trim($payload['note']) : null;
            }

            $logTableExists = $this->attendanceLogsTableExists();

            $result = DB::transaction(function () use ($u, $now, $workDate, $photoPath, $payload, $r, $logTableExists) {
                $mapping = ShiftMapping::lockForUpdate()
                    ->where('user_id', $u->id)
                    ->where('work_date', $workDate)
                    ->first();

                if (!$mapping) {
                    throw ValidationException::withMessages([
                        'type' => 'Jadwal tidak ditemukan untuk tanggal ini',
                    ]);
                }

                $shift = $mapping?->shift_id
                    ? $this->findShiftForCompany($mapping->shift_id, $u->company_id)
                    : null;

                $shiftTimezone = $shift?->timezone ?? 'Asia/Jakarta';
                $nowShiftTz = $now->copy()->setTimezone($shiftTimezone);
                $shiftStart = $this->resolveShiftDateTime($shift?->start_time, $workDate, $shiftTimezone);
                $shiftEnd = $this->resolveShiftDateTime($shift?->end_time, $workDate, $shiftTimezone);
                if ($shiftStart && $shiftEnd && $shiftEnd->lessThanOrEqualTo($shiftStart)) {
                    $shiftEnd = $shiftEnd->copy()->addDay();
                }

                $type = $r->type;
                $update = ['updated_at' => now()];
                $geofence = null;
                if ($payload['geofence_id'] ?? null) {
                    $geofence = $this->findGeofence($payload['geofence_id'], $u->company_id);
                }

                if ($type === 'clock_in') {
                    if (($mapping->lock_location ?? false) && (!$payload['latitude'] || !$payload['longitude'])) {
                        throw ValidationException::withMessages([
                            'latitude' => 'Lokasi wajib diisi saat lock lokasi aktif.',
                        ]);
                    }
                    if (($mapping->lock_location ?? false) && !$geofence) {
                        throw ValidationException::withMessages([
                            'geofence_id' => 'Geofence wajib dipilih saat lock lokasi aktif.',
                        ]);
                    }
                    $checkinDistance = $this->calculateDistanceForGeofence($payload['latitude'] ?? null, $payload['longitude'] ?? null, $geofence);
                    if ($mapping->lock_location && !is_null($checkinDistance) && $geofence && $checkinDistance > (float) $geofence->radius_m) {
                        throw ValidationException::withMessages([
                            'latitude' => 'Lokasi berada di luar radius yang diizinkan.',
                        ]);
                    }
                    $update = array_merge($update, [
                        'checkin_time' => $now,
                        'checkin_lat' => $payload['latitude'],
                        'checkin_lng' => $payload['longitude'],
                        'checkin_photo' => $photoPath,
                        'checkin_note' => $payload['note'] ?? null,
                        'checkin_distance' => is_null($checkinDistance) ? null : round($checkinDistance, 2),
                        'checkin_address' => $payload['address'] ?? $mapping->checkin_address,
                    ]);
                    if (is_null($mapping->attendance_status)) {
                        $update['attendance_status'] = 'hadir';
                    }
                    if (is_null($mapping->late) && $shiftStart) {
                        $lateMinutes = $shiftStart->diffInMinutes($nowShiftTz, false);
                        $update['late'] = $lateMinutes > 0 ? $lateMinutes : 0;
                    }
                } elseif ($type === 'clock_out') {
                    if (($mapping->lock_location ?? false) && (!$payload['latitude'] || !$payload['longitude'])) {
                        throw ValidationException::withMessages([
                            'latitude' => 'Lokasi wajib diisi saat lock lokasi aktif.',
                        ]);
                    }
                    if (($mapping->lock_location ?? false) && !$geofence) {
                        throw ValidationException::withMessages([
                            'geofence_id' => 'Geofence wajib dipilih saat lock lokasi aktif.',
                        ]);
                    }
                    $checkoutDistance = $this->calculateDistanceForGeofence($payload['latitude'] ?? null, $payload['longitude'] ?? null, $geofence);
                    if ($mapping->lock_location && !is_null($checkoutDistance) && $geofence && $checkoutDistance > (float) $geofence->radius_m) {
                        throw ValidationException::withMessages([
                            'latitude' => 'Lokasi berada di luar radius yang diizinkan.',
                        ]);
                    }
                    $update = array_merge($update, [
                        'checkout_time' => $now,
                        'checkout_lat' => $payload['latitude'],
                        'checkout_lng' => $payload['longitude'],
                        'checkout_photo' => $photoPath,
                        'checkout_note' => $payload['note'] ?? null,
                        'checkout_distance' => is_null($checkoutDistance) ? null : round($checkoutDistance, 2),
                        'checkout_address' => $payload['address'] ?? $mapping->checkout_address,
                    ]);
                    if (is_null($mapping->attendance_status) || $mapping->attendance_status === 'scheduled') {
                        $update['attendance_status'] = 'hadir';
                    }
                    if (is_null($mapping->early_checkout) && $shiftEnd) {
                        $earlyMinutes = $nowShiftTz->diffInMinutes($shiftEnd, false);
                        $update['early_checkout'] = $earlyMinutes > 0 ? $earlyMinutes : 0;
                    }
                } else {
                    $trail = trim((string) ($mapping->comment ?? ''));
                    $entry = sprintf('[%s @ %s] %s', strtoupper($type), $now->toDateTimeString(), $payload['note'] ?? '-');
                    $update['comment'] = trim($trail . PHP_EOL . $entry);
                }

                ShiftMapping::where('id', $mapping->id)->update($update);

                $logId = null;
                if ($logTableExists) {
                    $logId = (string) Str::orderedUuid();
                    DB::table('attendance_logs')->insert([
                        'id' => $logId,
                        'user_id' => $u->id,
                        'work_date' => $workDate,
                        'type' => $type,
                        'latitude' => $payload['latitude'],
                        'longitude' => $payload['longitude'],
                        'photo_url' => $photoPath,
                        'video_url' => $payload['video_url'] ?? null,
                        'device_info' => $payload['device_info'] ?? null,
                        'geofence_id' => $payload['geofence_id'] ?? null,
                        'note' => $payload['note'] ?? null,
                        'address' => $payload['address'] ?? null,
                        'logged_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                return [
                    'mapping_id' => $mapping->id,
                    'log_id' => $logId,
                ];
            });

            $updatedMapping = ShiftMapping::find($result['mapping_id']);

            $response = [
                'type' => $r->type,
                'work_date' => $workDate,
                'logged_at' => $now->toDateTimeString(),
                'mapping_id' => $result['mapping_id'],
                'photo_url' => $this->publicUrl($photoPath),
                'attendance' => $updatedMapping ? $this->transformMapping($updatedMapping, $workDate) : null,
            ];
            if ($result['log_id']) {
                $response['log_id'] = $result['log_id'];
            }

            return $this->ok($response, 'Clock berhasil', [], 201);
        } catch (ValidationException $e) {
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
            $logs = $this->loadLogsForDate($u->id, $date);

            return $this->ok(['date' => $date, 'logs' => $logs], 'Logs loaded');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat logs', 500, 'SERVER_ERROR');
        }
    }

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
            $latInput = $r->query('lat');
            $lngInput = $r->query('lng');
            $lat = is_null($latInput) ? null : (float) $latInput;
            $lng = is_null($lngInput) ? null : (float) $lngInput;

            $data = $this->buildOverviewData($u, $date, $lat, $lng);

            return $this->ok($data, 'Attendance overview');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat ringkasan absensi', 500, 'SERVER_ERROR');
        }
    }

    public function history(Request $r)
    {
        try {
            $validated = $r->validate([
                'date' => 'required|date',
            ]);

            $u = $r->user();
            if (!$u->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $timezone = config('app.timezone', 'UTC');
            $target = Carbon::parse($validated['date'], $timezone);
            $start = $target->copy()->startOfMonth();
            $end = $target->copy()->endOfMonth();

            $mappings = ShiftMapping::with('shift')
                ->where('user_id', $u->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->keyBy(function (ShiftMapping $mapping) {
                    $workDate = $mapping->work_date;
                    if ($workDate instanceof \DateTimeInterface) {
                        return $workDate->format('Y-m-d');
                    }
                    return Carbon::parse($workDate)->toDateString();
                });

            $dayOffStatuses = ['holiday', 'libur', 'off', 'leave', 'izin', 'cuti'];
            $absentStatuses = ['absent', 'alpha', 'tidak_hadir', 'tidak_masuk', 'not_present'];
            $dayOffKeywords = array_values(array_filter($dayOffStatuses, fn($keyword) => $keyword !== 'off'));
            $dayOffKeywords = array_values(array_unique(array_merge($dayOffKeywords, ['day off', 'day-off', 'off day', 'off-day'])));

            $summary = [
                'absent' => 0,
                'late_arrival' => 0,
                'early_checkout' => 0,
                'missing_clock_in' => 0,
                'missing_clock_out' => 0,
            ];

            $items = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $dateKey = $cursor->toDateString();
                /** @var ShiftMapping|null $mapping */
                $mapping = $mappings->get($dateKey);
                $shift = $mapping?->shift;

                $statusRaw = $mapping?->attendance_status;
                $status = $statusRaw ? strtolower($statusRaw) : null;
                $isDayOff = $status ? in_array($status, $dayOffStatuses, true) : false;

                if (!$isDayOff && $shift?->name) {
                    $shiftName = Str::of($shift->name)->lower()->trim();
                    $normalizedShiftName = preg_replace('/\s+/', ' ', (string) $shiftName);
                    if (in_array($normalizedShiftName, $dayOffStatuses, true)) {
                        $isDayOff = true;
                    } else {
                        if (Str::contains($normalizedShiftName, $dayOffKeywords)) {
                            $isDayOff = true;
                        }
                    }
                }
                $isAbsent = $status ? in_array($status, $absentStatuses, true) : false;

                $hasCheckinValue = $mapping && !empty($mapping->checkin_time);
                $hasCheckoutValue = $mapping && !empty($mapping->checkout_time);

                $clockIn = $mapping ? $this->formatHistoryTime($mapping->checkin_time, $dateKey) : null;
                $clockOut = $mapping ? $this->formatHistoryTime($mapping->checkout_time, $dateKey) : null;

                if (!$isDayOff) {
                    if ($mapping) {
                        if (!$hasCheckinValue && !$hasCheckoutValue && !$isAbsent) {
                            $isAbsent = true;
                        }
                    } else {
                        $isAbsent = false;
                    }
                }

                $lateRaw = $mapping?->late;
                $earlyRaw = $mapping?->early_checkout;
                $lateMinutes = (!$isDayOff && !$isAbsent && (int) ($lateRaw ?? 0) > 0) ? (int) $lateRaw : null;
                $earlyMinutes = (!$isDayOff && !$isAbsent && (int) ($earlyRaw ?? 0) > 0) ? (int) $earlyRaw : null;

                $missingClockIn = (!$isDayOff && !$isAbsent && $mapping && !$hasCheckinValue && $hasCheckoutValue);
                $missingClockOut = (!$isDayOff && !$isAbsent && $mapping && !$hasCheckoutValue && $hasCheckinValue);

                if ($isAbsent) {
                    $missingClockIn = false;
                    $missingClockOut = false;
                }

                if ($isAbsent) {
                    $summary['absent']++;
                }
                if (!is_null($lateMinutes)) {
                    $summary['late_arrival']++;
                }
                if (!is_null($earlyMinutes)) {
                    $summary['early_checkout']++;
                }
                if ($missingClockIn) {
                    $summary['missing_clock_in']++;
                }
                if ($missingClockOut) {
                    $summary['missing_clock_out']++;
                }

                $items[] = [
                    'date' => $dateKey,
                    'display_date' => $cursor->translatedFormat('j M'),
                    'weekday' => $cursor->translatedFormat('l'),
                    'log_id' => $mapping?->id,
                    'shift' => [
                        'id' => $shift?->id,
                        'name' => $shift?->name ?? ($isDayOff ? 'Libur' : null),
                    ],
                    'shift_type' => $shift?->name ?? ($isDayOff ? 'Libur' : null),
                    'attendance_status' => $statusRaw,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'late_minutes' => $lateMinutes,
                    'early_checkout_minutes' => $earlyMinutes,
                    'is_day_off' => $isDayOff,
                    'is_absent' => $isAbsent,
                    'flags' => [
                        'late' => !is_null($lateMinutes),
                        'early_checkout' => !is_null($earlyMinutes),
                        'no_clock_in' => $missingClockIn,
                        'no_clock_out' => $missingClockOut,
                    ],
                ];

                $cursor->addDay();
            }

            return $this->ok([
                'period' => [
                    'month' => $start->format('Y-m'),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'label' => $start->translatedFormat('F Y'),
                ],
                'summary' => $summary,
                'items' => $items,
            ], 'Riwayat absensi bulanan', [
                'requested_date' => $target->toDateString(),
                'timezone' => $timezone,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat riwayat absensi', 500, 'SERVER_ERROR');
        }
    }

    public function historyDetail(Request $r, string $mappingId)
    {
        try {
            $user = $r->user();
            if (!$user->is_employee) {
                return $this->fail('User bukan karyawan', 403, 'FORBIDDEN');
            }

            $mapping = ShiftMapping::with('shift')
                ->where('id', $mappingId)
                ->where('user_id', $user->id)
                ->first();

            if (!$mapping) {
                return $this->fail('Riwayat absensi tidak ditemukan', 404, 'NOT_FOUND');
            }

            $workDate = $mapping->work_date;
            if ($workDate instanceof \DateTimeInterface) {
                $workDate = $workDate->format('Y-m-d');
            } else {
                $workDate = Carbon::parse((string) $workDate)->toDateString();
            }

            $timezone = config('app.timezone', 'UTC');
            $dateObject = Carbon::parse($workDate, $timezone);
            $attendance = $this->transformMapping($mapping, $workDate);
            $logs = $this->loadLogsForDate($user->id, $workDate);

            return $this->ok([
                'id' => $mapping->id,
                'date' => $workDate,
                'display_date' => $dateObject->translatedFormat('j F Y'),
                'weekday' => $dateObject->translatedFormat('l'),
                'attendance_status' => $mapping->attendance_status,
                'shift' => [
                    'id' => $mapping->shift?->id,
                    'name' => $mapping->shift?->name,
                    'start_time' => $mapping->shift?->start_time,
                    'end_time' => $mapping->shift?->end_time,
                    'timezone' => $mapping->shift?->timezone,
                ],
                'clock_in' => $this->buildClockSegment($mapping, 'checkin', $workDate),
                'clock_out' => $this->buildClockSegment($mapping, 'checkout', $workDate),
                'logs' => $logs,
                'attendance' => $attendance,
            ], 'Detail riwayat absensi');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat detail riwayat absensi', 500, 'SERVER_ERROR');
        }
    }

    private function attendanceLogsTableExists(): bool
    {
        static $exists = null;
        if (is_null($exists)) {
            $exists = Schema::hasTable('attendance_logs');
        }

        return $exists;
    }

    private function buildOverviewData($user, string $date, ?float $lat, ?float $lng): array
    {
        $companyName = $this->resolveCompanyName($user->company_id);

        $locations = collect($this->loadGeofences($user->company_id))->map(fn($row) => (object) $row);

        $anyAllowed = null;
        if (!is_null($lat) && !is_null($lng)) {
            $locations = $locations->map(function ($g) use ($lat, $lng, &$anyAllowed) {
                $d = $this->distanceMeters($lat, $lng, (float) $g->latitude, (float) $g->longitude);
                $allowed = $d <= (float) $g->radius_m;
                $g->distance_m = (int) round($d);
                $g->allowed = $allowed;
                $anyAllowed = is_null($anyAllowed) ? $allowed : ($anyAllowed || $allowed);
                return $g;
            })->sortBy('distance_m')->values();
        }

        $mapping = ShiftMapping::where('user_id', $user->id)
            ->where('work_date', $date)
            ->first();

        $shift = null;
        if ($mapping && $mapping->shift_id) {
            $s = $this->findShiftForCompany($mapping->shift_id, $user->company_id);
            if ($s) {
                $shift = [
                    'id' => $s->id,
                    'name' => $s->name,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'timezone' => $s->timezone ?? null,
                ];
            }
        }

        $attendance = null;
        if ($mapping) {
            $attendance = $this->transformMapping($mapping, $date);
        }

        $logs = $this->loadLogsForDate($user->id, $date);

        return [
            'company' => [
                'id' => $user->company_id,
                'name' => $companyName,
            ],
            'date' => $date,
            'shift' => $shift,
            'attendance' => $attendance,
            'locations' => $locations,
            'any_allowed' => $anyAllowed,
            'logs' => $logs,
        ];
    }

    private function companiesTableExists(): bool
    {
        static $exists = null;
        if (is_null($exists)) {
            $exists = Schema::hasTable('companies');
        }

        return $exists;
    }

    private function resolveCompanyName(?string $companyId): ?string
    {
        if (!$companyId || !$this->companiesTableExists()) {
            return null;
        }

        try {
            return DB::table('companies')->where('id', $companyId)->value('name');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function loadGeofences(string $companyId): array
    {
        return DB::table('geofences')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude', 'radius_m'])
            ->map(fn($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'radius_m' => (float) $row->radius_m,
            ])
            ->all();
    }

    private function findShiftForCompany(string $shiftId, string $companyId)
    {
        $query = DB::table('shifts')->where('id', $shiftId);
        if ($this->shiftHasCompanyColumn()) {
            $query->where('company_id', $companyId);
        }
        return $query->first();
    }

    private function resolveShiftDateTime($timeValue, string $workDate, string $timezone): ?Carbon
    {
        if (empty($timeValue)) {
            return null;
        }

        try {
            if (is_string($timeValue) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeValue)) {
                return Carbon::createFromFormat('Y-m-d H:i:s', $workDate . ' ' . $timeValue, $timezone);
            }

            return Carbon::parse((string) $timeValue, $timezone);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatMappingTime($timeValue, string $workDate): ?string
    {
        if (empty($timeValue)) {
            return null;
        }

        if (is_string($timeValue) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeValue)) {
            return $workDate . ' ' . $timeValue;
        }

        try {
            return Carbon::parse((string) $timeValue, 'Asia/Jakarta')->toDateTimeString();
        } catch (\Throwable $e) {
            return (string) $timeValue;
        }
    }

    private function loadLogsForDate(string $userId, string $date)
    {
        if ($this->attendanceLogsTableExists()) {
            return DB::table('attendance_logs')
                ->where('user_id', $userId)
                ->where('work_date', $date)
                ->orderBy('logged_at')
                ->get()
                ->map(function ($row) {
                    $row->photo_url = $this->publicUrl($row->photo_url ?? null);
                    return $row;
                })
                ->values();
        }

        $mapping = ShiftMapping::where('user_id', $userId)
            ->whereDate('work_date', $date)
            ->first();

        $logs = collect();
        if ($mapping) {
            if ($mapping->checkin_time) {
                $logs->push((object) [
                    'type' => 'clock_in',
                    'logged_at' => $this->formatMappingTime($mapping->checkin_time, $date),
                    'note' => $mapping->checkin_note,
                    'photo_url' => $this->publicUrl($mapping->checkin_photo ?? null),
                    'latitude' => $mapping->checkin_lat,
                    'longitude' => $mapping->checkin_lng,
                    'distance' => is_null($mapping->checkin_distance) ? null : (float) $mapping->checkin_distance,
                    'late' => is_null($mapping->late) ? null : (int) $mapping->late,
                    'address' => $mapping->checkin_address,
                    'attendance_status' => $mapping->attendance_status,
                ]);
            }
            if ($mapping->checkout_time) {
                $logs->push((object) [
                    'type' => 'clock_out',
                    'logged_at' => $this->formatMappingTime($mapping->checkout_time, $date),
                    'note' => $mapping->checkout_note,
                    'photo_url' => $this->publicUrl($mapping->checkout_photo ?? null),
                    'latitude' => $mapping->checkout_lat,
                    'longitude' => $mapping->checkout_lng,
                    'distance' => is_null($mapping->checkout_distance) ? null : (float) $mapping->checkout_distance,
                    'early_checkout' => is_null($mapping->early_checkout) ? null : (int) $mapping->early_checkout,
                    'address' => $mapping->checkout_address,
                    'attendance_status' => $mapping->attendance_status,
                ]);
            }
            if (!empty($mapping->comment)) {
                foreach (preg_split('/\r\n|\n/', trim($mapping->comment)) as $line) {
                    if ($line === '') {
                        continue;
                    }
                    if (preg_match('/^\[(?<type>[A-Z_ ]+)\s*@\s*(?<time>[^]]+)\]\s*(?<note>.*)$/', $line, $matches)) {
                        $logs->push((object) [
                            'type' => strtolower(str_replace(' ', '_', $matches['type'])),
                            'logged_at' => $matches['time'],
                            'note' => $matches['note'] ?: null,
                            'photo_url' => null,
                            'latitude' => null,
                            'longitude' => null,
                        ]);
                    } else {
                        $logs->push((object) [
                            'type' => 'note',
                            'logged_at' => null,
                            'note' => $line,
                            'photo_url' => null,
                            'latitude' => null,
                            'longitude' => null,
                        ]);
                    }
                }
            }
        }

        return $logs->values();
    }

    private function shiftHasCompanyColumn(): bool
    {
        static $has = null;
        if (is_null($has)) {
            $has = Schema::hasColumn('shifts', 'company_id');
        }
        return $has;
    }

    private function transformMapping(ShiftMapping $mapping, string $fallbackDate): array
    {
        $workDate = $mapping->work_date;
        if ($workDate instanceof \DateTimeInterface) {
            $workDate = $workDate->format('Y-m-d');
        } elseif (!$workDate) {
            $workDate = $fallbackDate;
        }

        return [
            'id' => $mapping->id,
            'user_id' => $mapping->user_id,
            'shift_id' => $mapping->shift_id,
            'work_date' => $workDate,
            'checkin_time' => $this->formatMappingTime($mapping->checkin_time, $fallbackDate),
            'checkin_lat' => $mapping->checkin_lat,
            'checkin_lng' => $mapping->checkin_lng,
            'checkin_distance' => is_null($mapping->checkin_distance) ? null : (float) $mapping->checkin_distance,
            'checkin_address' => $mapping->checkin_address,
            'checkin_photo' => $this->publicUrl($mapping->checkin_photo ?? null),
            'checkin_note' => $mapping->checkin_note,
            'late' => is_null($mapping->late) ? null : (int) $mapping->late,
            'checkout_time' => $this->formatMappingTime($mapping->checkout_time, $fallbackDate),
            'checkout_lat' => $mapping->checkout_lat,
            'checkout_lng' => $mapping->checkout_lng,
            'checkout_distance' => is_null($mapping->checkout_distance) ? null : (float) $mapping->checkout_distance,
            'checkout_address' => $mapping->checkout_address,
            'checkout_photo' => $this->publicUrl($mapping->checkout_photo ?? null),
            'checkout_note' => $mapping->checkout_note,
            'early_checkout' => is_null($mapping->early_checkout) ? null : (int) $mapping->early_checkout,
            'attendance_status' => $mapping->attendance_status,
            'lock_location' => is_null($mapping->lock_location) ? null : (bool) $mapping->lock_location,
            'proposed_checkin_time' => $this->formatMappingTime($mapping->proposed_checkin_time, $fallbackDate),
            'proposed_checkout_time' => $this->formatMappingTime($mapping->proposed_checkout_time, $fallbackDate),
            'description' => $mapping->description,
            'request_status' => $mapping->request_status,
            'request_file' => $mapping->request_file,
            'comment' => $mapping->comment,
            'approved_by' => $mapping->approved_by,
            'created_at' => $mapping->created_at?->toDateTimeString(),
            'updated_at' => $mapping->updated_at?->toDateTimeString(),
        ];
    }

    private function findGeofence(?string $geofenceId, string $companyId)
    {
        if (!$geofenceId) {
            return null;
        }

        return DB::table('geofences')
            ->where('id', $geofenceId)
            ->where('company_id', $companyId)
            ->first();
    }

    private function calculateDistanceForGeofence(?float $lat, ?float $lng, ?object $geofence): ?float
    {
        if (is_null($lat) || is_null($lng) || !$geofence) {
            return null;
        }

        return $this->distanceMeters($lat, $lng, (float) $geofence->latitude, (float) $geofence->longitude);
    }

    private function formatHistoryTime($value, string $workDate): ?string
    {
        if (empty($value)) {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($timezone)->format('H:i');
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || str_contains($trimmed, '0000-00-00')) {
                return null;
            }

            try {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $trimmed)) {
                    $format = strlen($trimmed) === 5 ? 'H:i' : 'H:i:s';
                    return Carbon::createFromFormat($format, $trimmed, $timezone)->format('H:i');
                }

                return Carbon::parse($trimmed, $timezone)->format('H:i');
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($workDate . ' ' . $trimmed, $timezone)->format('H:i');
                } catch (\Throwable $ex) {
                    return $trimmed;
                }
            }
        }

        try {
            return Carbon::parse((string) $value, $timezone)->format('H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function buildClockSegment(ShiftMapping $mapping, string $segment, string $workDate): array
    {
        $prefix = $segment === 'checkout' ? 'checkout' : 'checkin';
        $timeField = $prefix . '_time';
        $latField = $prefix . '_lat';
        $lngField = $prefix . '_lng';
        $noteField = $prefix . '_note';
        $photoField = $prefix . '_photo';
        $addressField = $prefix . '_address';
        $distanceField = $prefix . '_distance';

        $data = [
            'time' => $this->formatHistoryTime($mapping->{$timeField}, $workDate),
            'recorded_at' => $this->formatMappingTime($mapping->{$timeField}, $workDate),
            'note' => $mapping->{$noteField},
            'photo_url' => $this->publicUrl($mapping->{$photoField} ?? null),
            'latitude' => is_null($mapping->{$latField}) ? null : (float) $mapping->{$latField},
            'longitude' => is_null($mapping->{$lngField}) ? null : (float) $mapping->{$lngField},
            'address' => $mapping->{$addressField},
            'distance_m' => is_null($mapping->{$distanceField}) ? null : (float) $mapping->{$distanceField},
        ];

        if ($prefix === 'checkin') {
            $data['late_minutes'] = is_null($mapping->late) ? null : (int) $mapping->late;
        } else {
            $data['early_checkout_minutes'] = is_null($mapping->early_checkout) ? null : (int) $mapping->early_checkout;
        }

        return $data;
    }
}
