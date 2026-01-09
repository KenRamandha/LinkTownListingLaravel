<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ModulesController extends Controller
{
    // GET /api/core/modules - Ambil daftar modul
    public function index(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('modules:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $companyId = $r->user()->company_id;
            $data = DB::table('modules')
                ->where('company_id', $companyId)
                ->orderBy('sort_order')
                ->get();
            return $this->ok($data, 'Modules');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat modules', 500, 'SERVER_ERROR');
        }
    }

    // POST /api/core/modules - Buat modul baru
    public function store(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('modules:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'key' => 'required|string|max:80',
                'name' => 'required|string|max:120',
                'is_active' => 'required|boolean',
                'sort_order' => 'required|integer',
            ]);
            $user = $r->user();
            $id = (string) Str::orderedUuid();
            DB::table('modules')->insert([
                'id' => $id,
                'company_id' => $user->company_id,
                'key' => $r->key,
                'name' => $r->name,
                'is_active' => $r->is_active,
                'sort_order' => $r->sort_order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->ok(['id' => $id], 'Module dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat module', 500, 'SERVER_ERROR');
        }
    }

    // PUT /api/core/modules/{id} - Update modul
    public function update(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('modules:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'key' => 'nullable|string|max:80',
                'name' => 'nullable|string|max:120',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            $user = $r->user();
            $exists = DB::table('modules')
                ->where('company_id', $user->company_id)
                ->where('id', $id)
                ->exists();
            if (!$exists) return $this->fail('Module tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('key', 'name', 'is_active', 'sort_order'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('modules')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Module diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui module', 500, 'SERVER_ERROR');
        }
    }

    // DELETE /api/core/modules/{id} - Hapus modul
    public function destroy(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('modules:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $count = DB::table('modules')
                ->where('company_id', $r->user()->company_id)
                ->where('id', $id)
                ->delete();
            if (!$count) return $this->fail('Module tidak ditemukan', 404, 'NOT_FOUND');
            return $this->ok(null, 'Module dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus module', 500, 'SERVER_ERROR');
        }
    }
}
