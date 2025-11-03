<?php


namespace App\Http\Controllers\Menus;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MenuAdminController extends Controller
{
    public function menus(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('menus:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $data = DB::table('menus')->where('company_id', $u->company_id)->orderBy('name')->get();
            return $this->ok($data, 'Menus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat menus', 500, 'SERVER_ERROR');
        }
    }
    public function createMenu(Request $r)
    {
        try {
            if (!$r->user()->hasPermission('menus:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate(['name' => 'required|string|max:120', 'key' => 'required|string|max:80', 'is_active' => 'required|boolean']);
            $u = $r->user();
            $id = (string)Str::orderedUuid();
            DB::table('menus')->insert(['id' => $id, 'company_id' => $u->company_id, 'name' => $r->name, 'key' => $r->key, 'is_active' => $r->is_active, 'created_at' => now(), 'updated_at' => now()]);
            return $this->ok(['id' => $id], 'Menu dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat menu', 500, 'SERVER_ERROR');
        }
    }
    public function updateMenu(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('menus:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate(['name' => 'nullable|string|max:120', 'key' => 'nullable|string|max:80', 'is_active' => 'nullable|boolean']);
            $u = $r->user();
            $menu = DB::table('menus')->where('company_id', $u->company_id)->where('id', $id)->first();
            if (!$menu) return $this->fail('Menu tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only('name', 'key', 'is_active'), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('menus')->where('id', $id)->update($upd);
            return $this->ok(['id' => $id], 'Menu diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui menu', 500, 'SERVER_ERROR');
        }
    }
    public function deleteMenu(Request $r, string $id)
    {
        try {
            if (!$r->user()->hasPermission('menus:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $u = $r->user();
            $menu = DB::table('menus')->where('company_id', $u->company_id)->where('id', $id)->first();
            if (!$menu) return $this->fail('Menu tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('menus')->where('id', $id)->delete();
            return $this->ok(null, 'Menu dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus menu', 500, 'SERVER_ERROR');
        }
    }

    public function items(Request $r, string $menuId)
    {
        try {
            if (!$r->user()->hasPermission('menu_items:view')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $data = DB::table('menu_items')->where('menu_id', $menuId)->orderBy('sort_order')->get();
            return $this->ok($data, 'Menu items');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat menu items', 500, 'SERVER_ERROR');
        }
    }
    public function createItem(Request $r, string $menuId)
    {
        try {
            if (!$r->user()->hasPermission('menu_items:create')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'parent_id' => 'nullable|string',
                'type' => 'required|in:link,group,external',
                'label' => 'required|string|max:120',
                'icon' => 'nullable|string|max:80',
                'route' => 'nullable|string|max:180',
                'url_external' => 'nullable|string|max:255',
                'module_id' => 'nullable|string',
                'feature_id' => 'nullable|string',
                'permission_key' => 'nullable|string|max:160',
                'visible_if_employee' => 'nullable|boolean',
                'platform' => 'required|string|max:16',
                'sort_order' => 'required|integer',
                'is_divider' => 'required|boolean',
                'badge_expr' => 'nullable|string|max:160',
                'meta_json' => 'nullable'
            ]);
            $id = (string)Str::orderedUuid();
            DB::table('menu_items')->insert(array_merge($r->only(['parent_id', 'type', 'label', 'icon', 'route', 'url_external', 'module_id', 'feature_id', 'permission_key', 'visible_if_employee', 'platform', 'sort_order', 'is_divider', 'badge_expr', 'meta_json']), [
                'id' => $id,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
            return $this->ok(['id' => $id], 'Menu item dibuat', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal membuat menu item', 500, 'SERVER_ERROR');
        }
    }
    public function updateItem(Request $r, string $itemId)
    {
        try {
            if (!$r->user()->hasPermission('menu_items:update')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $r->validate([
                'parent_id' => 'nullable|string',
                'type' => 'nullable|in:link,group,external',
                'label' => 'nullable|string|max:120',
                'icon' => 'nullable|string|max:80',
                'route' => 'nullable|string|max:180',
                'url_external' => 'nullable|string|max:255',
                'module_id' => 'nullable|string',
                'feature_id' => 'nullable|string',
                'permission_key' => 'nullable|string|max:160',
                'visible_if_employee' => 'nullable|boolean',
                'platform' => 'nullable|string|max:16',
                'sort_order' => 'nullable|integer',
                'is_divider' => 'nullable|boolean',
                'badge_expr' => 'nullable|string|max:160',
                'meta_json' => 'nullable'
            ]);
            $item = DB::table('menu_items')->where('id', $itemId)->first();
            if (!$item) return $this->fail('Menu item tidak ditemukan', 404, 'NOT_FOUND');
            $upd = array_filter($r->only(['parent_id', 'type', 'label', 'icon', 'route', 'url_external', 'module_id', 'feature_id', 'permission_key', 'visible_if_employee', 'platform', 'sort_order', 'is_divider', 'badge_expr', 'meta_json']), fn($v) => !is_null($v));
            $upd['updated_at'] = now();
            DB::table('menu_items')->where('id', $itemId)->update($upd);
            return $this->ok(['id' => $itemId], 'Menu item diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memperbarui menu item', 500, 'SERVER_ERROR');
        }
    }
    public function deleteItem(Request $r, string $itemId)
    {
        try {
            if (!$r->user()->hasPermission('menu_items:delete')) return $this->fail('Forbidden', 403, 'FORBIDDEN');
            $item = DB::table('menu_items')->where('id', $itemId)->first();
            if (!$item) return $this->fail('Menu item tidak ditemukan', 404, 'NOT_FOUND');
            DB::table('menu_items')->where('id', $itemId)->delete();
            return $this->ok(null, 'Menu item dihapus');
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal menghapus menu item', 500, 'SERVER_ERROR');
        }
    }
}
