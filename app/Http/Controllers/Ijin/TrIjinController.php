<?php

namespace App\Http\Controllers\Ijin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TrIjinController extends Controller
{
    public function today(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            $data = DB::table('tr_ijin')
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->whereDate('tgl_dari', '<=', $today)
                ->whereDate('tgl_sampai', '>=', $today)
                ->orderByDesc('created_at')
                ->get();

            return $this->ok($data, 'Daftar izin hari ini');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat data izin hari ini', 500, 'SERVER_ERROR');
        }
    }

    public function history(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
                'per_page'   => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();
            $perPage = $validated['per_page'] ?? 15;

            $query = DB::table('tr_ijin')
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->orderByDesc('created_at');

            if ($validated['start_date'] ?? null) {
                $query->whereDate('tgl_dari', '>=', Carbon::parse($validated['start_date'])->toDateString());
            }

            if ($validated['end_date'] ?? null) {
                $query->whereDate('tgl_dari', '<=', Carbon::parse($validated['end_date'])->toDateString());
            }

            $data = $query->paginate($perPage);

            return $this->ok($data, 'Daftar riwayat izin');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat riwayat izin', 500, 'SERVER_ERROR');
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            $ijin = DB::table('tr_ijin')
                ->where('id', $id)
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->first();

            if (!$ijin) {
                return $this->fail('Izin tidak ditemukan', 404, 'NOT_FOUND');
            }

            return $this->ok($ijin, 'Detail izin');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat detail izin', 500, 'SERVER_ERROR');
        }
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $data = DB::table('tr_ijin')
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get();

            return $this->ok($data, 'Daftar izin berhasil dimuat');
        } catch (\Exception $e) {
            report($e);
            return $this->fail('Gagal memuat data izin', 500, 'SERVER_ERROR');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'jenis_form'   => 'required|string|max:20',
                'nama'         => 'required|string|max:255',
                'tgl_dari'     => 'required|date',
                'tgl_sampai'   => 'required|date|after_or_equal:tgl_dari',
                'tujuan'       => 'nullable|string|max:255',
                'remark1'      => 'nullable|string|max:255',
                'remark2'      => 'nullable|string|max:255',
                'note'         => 'nullable|string',
                'file'         => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi|max:5120',
            ]);

            $user = $request->user();

            $imageUpload = null;
            if ($request->hasFile('file')) {
                $filePath = $this->storeFile($request->file('file'), $user->id);
                $imageUpload = $this->publicUrl($filePath);
            }

            $ijinId = DB::table('tr_ijin')->insertGetId([
                'id_user'       => $user->id,
                'jenis_form'    => $validated['jenis_form'],
                'nama'          => $validated['nama'],
                'tgl_dari'      => Carbon::parse($validated['tgl_dari'])->toDateTimeString(),
                'tgl_sampai'    => Carbon::parse($validated['tgl_sampai'])->toDateTimeString(),
                'tujuan'        => $validated['tujuan'] ?? null,
                'remark1'       => $validated['remark1'] ?? null,
                'remark2'       => $validated['remark2'] ?? null,
                'note'          => $validated['note'] ?? null,
                'image_upload'  => $imageUpload,
                'created_at'    => now(),
                'create_user'   => $user->name ?? $user->id,
                'status_ijin'   => 'Request',
            ]);

            $ijin = DB::table('tr_ijin')->where('id', $ijinId)->first();

            return $this->ok($ijin, 'Izin berhasil dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('Validasi gagal', 422, 'VALIDATION_ERROR', $e->errors());
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat izin', 500, 'SERVER_ERROR');
        }
    }

    /**
     * PUT /api/ijin/{id} - Update existing izin
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('Ijin Update Request:', [
                'id' => $id,
                'all' => $request->all(),
                'input' => $request->input(),
                'has jenis_form' => $request->has('jenis_form'),
                'has nama' => $request->has('nama'),
            ]);

            $user = $request->user();

            // Check if izin exists and belongs to user
            $ijin = DB::table('tr_ijin')
                ->where('id', $id)
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->first();

            if (!$ijin) {
                return $this->fail('Izin tidak ditemukan', 404, 'NOT_FOUND');
            }

            // Validate with conditional rules
            $rules = [
                'nama'         => 'nullable|string|max:255',
                'tujuan'       => 'nullable|string|max:255',
                'remark1'      => 'nullable|string|max:255',
                'remark2'      => 'nullable|string|max:255',
                'note'         => 'nullable|string',
                'file'         => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi|max:5120',
                'jenis_form'   => 'nullable|string|max:20',
                'tgl_dari'     => 'nullable|date',
                'tgl_sampai'   => 'nullable|date',
            ];

            $request->validate($rules);

            // Additional validation for tgl_sampai after tgl_dari
            if ($request->filled('tgl_dari') && $request->filled('tgl_sampai')) {
                $tglDari = Carbon::parse($request->tgl_dari);
                $tglSampai = Carbon::parse($request->tgl_sampai);
                if ($tglSampai->lessThan($tglDari)) {
                    return $this->fail('Tanggal sampai harus sama atau setelah tanggal dari', 422, 'VALIDATION_ERROR');
                }
            }

            // Handle file upload - delete old file if exists
            $imageUpload = $ijin->image_upload;
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($ijin->image_upload) {
                    $oldPath = str_replace(asset('storage/'), '', $ijin->image_upload);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $filePath = $this->storeFile($request->file('file'), $user->id);
                $imageUpload = $this->publicUrl($filePath);
            }

            // Get all request data except file and method fields
            $input = $request->except(['_method', 'file']);

            // Build update data
            $updateData = ['updated_at' => now()];

            // Add fields that exist in input (including empty strings)
            if (array_key_exists('jenis_form', $input)) {
                $updateData['jenis_form'] = $input['jenis_form'];
            }
            if (array_key_exists('nama', $input)) {
                $updateData['nama'] = $input['nama'];
            }
            if (array_key_exists('tgl_dari', $input) && $input['tgl_dari']) {
                $updateData['tgl_dari'] = Carbon::parse($input['tgl_dari'])->toDateTimeString();
            }
            if (array_key_exists('tgl_sampai', $input) && $input['tgl_sampai']) {
                $updateData['tgl_sampai'] = Carbon::parse($input['tgl_sampai'])->toDateTimeString();
            }
            if (array_key_exists('tujuan', $input)) {
                $updateData['tujuan'] = $input['tujuan'];
            }
            if (array_key_exists('remark1', $input)) {
                $updateData['remark1'] = $input['remark1'];
            }
            if (array_key_exists('remark2', $input)) {
                $updateData['remark2'] = $input['remark2'];
            }
            if (array_key_exists('note', $input)) {
                $updateData['note'] = $input['note'];
            }
            if ($request->hasFile('file')) {
                $updateData['image_upload'] = $imageUpload;
            }

            DB::table('tr_ijin')
                ->where('id', $id)
                ->update($updateData);

            $updatedIjin = DB::table('tr_ijin')->where('id', $id)->first();

            return $this->ok($updatedIjin, 'Izin berhasil diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('Validasi gagal', 422, 'VALIDATION_ERROR', $e->errors());
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui izin', 500, 'SERVER_ERROR');
        }
    }

    /**
     * DELETE /api/ijin/{id} - Soft delete izin
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Check if izin exists and belongs to user
            $ijin = DB::table('tr_ijin')
                ->where('id', $id)
                ->where('id_user', $user->id)
                ->where('status_ijin', '!=', 'Hapus')
                ->whereNull('deleted_at')
                ->first();

            if (!$ijin) {
                return $this->fail('Izin tidak ditemukan', 404, 'NOT_FOUND');
            }

            // Soft delete: set deleted_at and status_ijin to 'Hapus'
            DB::table('tr_ijin')
                ->where('id', $id)
                ->update([
                    'deleted_at' => now(),
                    'status_ijin' => 'Hapus',
                    'updated_at' => now(),
                ]);

            return $this->ok(null, 'Ijin berhasil dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus izin', 500, 'SERVER_ERROR');
        }
    }

    private function storeFile($file, string $userId): ?string
    {
        if (!$file) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = sprintf(
            'ijin_%s_%s.%s',
            $userId,
            now()->format('Ymd_His') . '_' . Str::random(6),
            $ext
        );

        return $file->storeAs('ijin', $filename, 'public');
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) return null;
        $path = ltrim($path, '/');
        return asset('storage/' . $path);
    }
}
