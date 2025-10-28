<?php

namespace App\Http\Controllers\Visits;

use App\Http\Controllers\Controller;
use App\Models\Visits\Visit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VisitController extends Controller
{
    public function clock(Request $request)
    {
        try {
            $this->prepareClockPayload($request);

            $validated = $request->validate([
                'type'        => 'required|in:check_in,check_out',
                'tanggal'     => 'nullable|date',
                'visit_time'  => 'nullable|date',
                'keterangan'  => 'required|string',
                'address'     => 'required|string',
                'lat'         => 'nullable|string',
                'long'        => 'nullable|string',
                'visit_id'    => 'nullable|string',
                'foto'        => 'nullable|file|image|max:5120',
            ]);

            $user = $request->user();
            $type = $validated['type'];
            $latProvided = array_key_exists('lat', $validated);
            $longProvided = array_key_exists('long', $validated);
            $note = trim($validated['keterangan']);
            $address = trim($validated['address']);

            if ($address === '') {
                throw ValidationException::withMessages([
                    'address' => 'Alamat wajib diisi.',
                ]);
            }
            $uploadedPhoto = $request->file('foto') ?? $request->file('photo');
            $photoPath = $this->storePhoto($uploadedPhoto, $user->id, $type);

            if ($type === 'check_in') {
                $tanggal = isset($validated['tanggal'])
                    ? Carbon::parse($validated['tanggal'])->toDateString()
                    : now()->toDateString();

                $visitTime = isset($validated['visit_time'])
                    ? Carbon::parse($validated['visit_time'])
                    : now();

                $visit = DB::transaction(function () use ($user, $validated, $tanggal, $visitTime, $photoPath, $note, $latProvided, $longProvided, $address) {
                    $openVisit = Visit::query()
                        ->where('user_id', $user->id)
                        ->whereNull('visit_out')
                        ->lockForUpdate()
                        ->first();

                    if ($openVisit) {
                        throw ValidationException::withMessages([
                            'type' => 'Masih ada kunjungan yang belum diselesaikan.',
                        ]);
                    }

                    $id = (string) Str::orderedUuid();

                    return Visit::create([
                        'id'            => $id,
                        'user_id'       => $user->id,
                        'tanggal'       => $tanggal,
                        'visit_in'      => $visitTime,
                        'foto_in'       => $photoPath,
                        'address_in'    => $address,
                        'lat_in'        => $latProvided ? ($validated['lat'] ?? null) : null,
                        'long_in'       => $longProvided ? ($validated['long'] ?? null) : null,
                        'keterangan_in' => $note,
                        'status'        => '0',
                    ]);
                });

                return $this->ok(
                    $this->transformVisit($visit),
                    'Kunjungan berhasil dimulai',
                    [],
                    201
                );
            }

            $visitTime = isset($validated['visit_time'])
                ? Carbon::parse($validated['visit_time'])
                : now();

            $visit = DB::transaction(function () use ($user, $validated, $visitTime, $photoPath, $note, $latProvided, $longProvided, $address) {
                $query = Visit::query()
                    ->where('user_id', $user->id)
                    ->whereNull('visit_out');

                if (!empty($validated['visit_id'])) {
                    $query->where('id', $validated['visit_id']);
                }

                $locked = $query->lockForUpdate()->first();

                if (!$locked) {
                    throw ValidationException::withMessages([
                        'visit_id' => 'Kunjungan aktif tidak ditemukan.',
                    ]);
                }

                if (!is_null($locked->visit_out)) {
                    throw ValidationException::withMessages([
                        'visit_id' => 'Kunjungan sudah selesai.',
                    ]);
                }

                $update = [
                    'visit_out'      => $visitTime,
                    'keterangan_out' => $note,
                    'address_out'    => $address,
                ];

                if ($latProvided) {
                    $update['lat_out'] = $validated['lat'] ?? null;
                }
                if ($longProvided) {
                    $update['long_out'] = $validated['long'] ?? null;
                }
                if ($photoPath) {
                    $update['foto_out'] = $photoPath;
                }

                $locked->fill($update);
                $locked->save();

                return $locked->fresh();
            });

            return $this->ok(
                $this->transformVisit($visit),
                'Kunjungan berhasil diselesaikan'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memproses kunjungan', 500, 'SERVER_ERROR');
        }
    }

    public function today(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            $items = Visit::query()
                ->where('user_id', $user->id)
                ->whereDate('tanggal', $today)
                ->orderByDesc('visit_in')
                ->get()
                ->map(fn(Visit $visit) => $this->transformVisit($visit))
                ->all();

            return $this->ok(
                ['items' => $items],
                'Daftar kunjungan hari ini',
                ['tanggal' => $today]
            );
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat kunjungan hari ini', 500, 'SERVER_ERROR');
        }
    }

    public function show(Request $request, Visit $visit)
    {
        try {
            if ($visit->user_id !== $request->user()->id) {
                return $this->fail('Kunjungan tidak ditemukan', 404, 'NOT_FOUND');
            }

            return $this->ok(
                $this->transformVisit($visit->loadMissing('user')),
                'Detail kunjungan'
            );
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat detail kunjungan', 500, 'SERVER_ERROR');
        }
    }

    public function history(Request $request)
    {
        try {
            $validated = $request->validate([
                'tanggal_mulai'   => 'nullable|date',
                'tanggal_selesai' => 'nullable|date',
                'status'          => 'nullable|string',
                'per_page'        => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();
            $perPage = $validated['per_page'] ?? 20;

            $query = Visit::query()
                ->where('user_id', $user->id)
                ->orderByDesc('tanggal')
                ->orderByDesc('visit_in');

            if ($validated['tanggal_mulai'] ?? null) {
                $query->whereDate('tanggal', '>=', Carbon::parse($validated['tanggal_mulai'])->toDateString());
            }

            if ($validated['tanggal_selesai'] ?? null) {
                $query->whereDate('tanggal', '<=', Carbon::parse($validated['tanggal_selesai'])->toDateString());
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            /** @var LengthAwarePaginator $paginator */
            $paginator = $query->paginate($perPage)->withQueryString();

            $items = $paginator->getCollection()
                ->map(fn(Visit $visit) => $this->transformVisit($visit))
                ->values()
                ->all();

            $paginator->setCollection(collect($items));

            return $this->ok(
                [
                    'items'      => $items,
                    'pagination' => [
                        'total'        => $paginator->total(),
                        'per_page'     => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                    ],
                ],
                'Riwayat kunjungan'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat riwayat kunjungan', 500, 'SERVER_ERROR');
        }
    }

    private function transformVisit(Visit $visit): array
    {
        $visit->refresh();

        return [
            'id'              => $visit->id,
            'user_id'         => $visit->user_id,
            'tanggal'         => optional($visit->tanggal)->toDateString(),
            'visit_in'        => optional($visit->visit_in)->toDateTimeString(),
            'foto_in'         => $visit->foto_in,
            'foto_in_url'     => $this->publicUrl($visit->foto_in),
            'address_in'      => $visit->address_in,
            'lat_in'          => $visit->lat_in,
            'long_in'         => $visit->long_in,
            'keterangan_in'   => $visit->keterangan_in,
            'visit_out'       => optional($visit->visit_out)->toDateTimeString(),
            'foto_out'        => $visit->foto_out,
            'foto_out_url'    => $this->publicUrl($visit->foto_out),
            'address_out'     => $visit->address_out,
            'lat_out'         => $visit->lat_out,
            'long_out'        => $visit->long_out,
            'keterangan_out'  => $visit->keterangan_out,
            'status'          => $visit->status ?? '0',
            'created_at'      => optional($visit->created_at)->toDateTimeString(),
            'updated_at'      => optional($visit->updated_at)->toDateTimeString(),
            'user'            => $visit->relationLoaded('user')
                ? [
                    'id'   => $visit->user?->id,
                    'name' => $visit->user?->name,
                ]
                : null,
        ];
    }

    private function prepareClockPayload(Request $request): void
    {
        if (!$request->filled('keterangan')) {
            foreach (['keterangan_in', 'keterangan_out'] as $key) {
                if ($request->filled($key)) {
                    $request->merge(['keterangan' => $request->input($key)]);
                    break;
                }
            }
        }

        if (!$request->filled('address')) {
            foreach (['address_in', 'address_out'] as $key) {
                if ($request->filled($key)) {
                    $request->merge(['address' => $request->input($key)]);
                    break;
                }
            }
        }

        if (!$request->filled('visit_time')) {
            foreach (['visit_in', 'visit_out'] as $key) {
                if ($request->filled($key)) {
                    $request->merge(['visit_time' => $request->input($key)]);
                    break;
                }
            }
        }

        if (!$request->has('lat')) {
            foreach (['lat_in', 'lat_out', 'latitude'] as $key) {
                if ($request->exists($key)) {
                    $request->merge(['lat' => $request->input($key)]);
                    break;
                }
            }
        }

        if (!$request->has('long')) {
            foreach (['long_in', 'long_out', 'lng', 'longitude'] as $key) {
                if ($request->exists($key)) {
                    $request->merge(['long' => $request->input($key)]);
                    break;
                }
            }
        }
    }

    private function storePhoto($file, string $userId, string $type): ?string
    {
        if (!$file) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = sprintf(
            '%s_%s_%s.%s',
            $type === 'check_in' ? 'in' : 'out',
            $userId,
            now()->format('Ymd_His') . '_' . Str::random(6),
            $ext
        );

        return $file->storeAs('visit', $filename, 'public');
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) return null;
        $path = ltrim($path, '/');
        return asset('storage/' . $path);
    }
}
