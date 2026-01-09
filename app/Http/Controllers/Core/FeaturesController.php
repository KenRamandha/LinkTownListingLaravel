<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FeaturesController extends Controller
{
    // GET /api/core/features - Ambil daftar fitur dengan filter modul
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('features:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->user()->company_id;
            $q = DB::table('features')
                ->join('modules', 'modules.id', '=', 'features.module_id')
                ->where('modules.company_id', $companyId)
                ->select('features.*');
            if ($mid = $r->query('module_id')) {
                $q->where('features.module_id', $mid);
            }
            $data = $q->orderBy('features.name')->get();
            return $this->ok($data, 'Features');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat features', 500, 'SERVER_ERROR');
        }
    }

    // POST /api/core/features - Buat fitur baru
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('features:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'module_id' => 'required|string',
                'key' => 'required|string|max:120',
                'name' => 'required|string|max:150',
                'description' => 'nullable|string',
            ]);
            $module = DB::table('modules')
                ->where('id', $r->module_id)
                ->where('company_id', $r->user()->company_id)
                ->first();
            if (!$module) return $this->fail('Module tidak ditemukan', 404, 'NOT_FOUND');
            $id = (string) Str::orderedUuid();
            DB::table('features')->insert([
                'id' => $id,
                'module_id' => $r->module_id,
                'key' => $r->key,
                'name' => $r->name,
                'description' => $r->description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Feature dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat feature', 500, 'SERVER_ERROR');
        }
    }

    // PUT /api/core/features/{id} - Update fitur
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('features:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'module_id' => 'nullable|string',
                'key' => 'nullable|string|max:120',
                'name' => 'nullable|string|max:150',
                'description' => 'nullable|string',
            ]);
            $companyId = $r->user()->company_id;
            $feature = DB::table('features')
                ->join('modules', 'modules.id', '=', 'features.module_id')
                ->where('features.id', $id)
                ->where('modules.company_id', $companyId)
                ->select('features.id')
                ->first();
            if (!$feature) return $this->fail('Feature tidak ditemukan', 404, 'NOT_FOUND');

            $upd = array_filter($r->only('module_id', 'key', 'name', 'description'), fn($v) => !is_null($v));
            if (isset($upd['module_id'])) {
                $module = DB::table('modules')
                    ->where('id', $upd['module_id'])
                    ->where('company_id', $companyId)
                    ->first();
                if (!$module) return $this->fail('Module tidak ditemukan', 404, 'NOT_FOUND');
            }
            $upd['updated_at'] = now();
            DB::table('features')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Feature diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui feature', 500, 'SERVER_ERROR');
        }
    }

    // DELETE /api/core/features/{id} - Hapus fitur
    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('features:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->user()->company_id;
            $feature = DB::table('features')
                ->join('modules', 'modules.id', '=', 'features.module_id')
                ->where('features.id', $id)
                ->where('modules.company_id', $companyId)
                ->select('features.id')
                ->first();
            if (!$feature) return $this->fail('Feature tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('features')->where('id', $id)->delete();
            return $this->ok(null, 'Feature dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus feature', 500, 'SERVER_ERROR');
        }
    }
}
