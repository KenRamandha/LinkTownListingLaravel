<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MenuController extends Controller
{
    public function showPublicAware(Request $r, string $key)
    {
        try {
            $user = $r->user();
            $companyId = $user->company_id ?? 'CMP-ACME';

            $menu = DB::table('menus')->where('company_id', $companyId)->where('key', $key)->first();
            if (!$menu) return $this->fail('Menu tidak ditemukan', 404, 'NOT_FOUND');

            $items = DB::table('menu_items')->where('menu_id', $menu->id)->orderBy('sort_order')->get();

            $visibleForGuestRoot = ['Home', 'Profile'];
            $has = function ($perm) use ($user) {
                if (!$user) return false;
                if (!$perm) return true;
                return $user->hasPermission($perm);
            };

            $tree = [];
            $byId = [];
            $homeNode = null;
            $profileNode = null;

            foreach ($items as $it) {
                $visible = true;

                if ($user) {
                    if ($it->permission_key && !$has($it->permission_key)) $visible = false;
                } else {
                    $isRoot = $it->parent_id === null;
                    $visible = $isRoot && in_array($it->label, $visibleForGuestRoot, true);
                }

                if (!$visible) continue;

                $node = [
                    'id' => $it->id,
                    'type' => $it->type,
                    'label' => $it->label,
                    'icon' => $it->icon,
                    'route' => $it->route,
                    'children' => []
                ];
                $byId[$it->id] = $node;

                if ($it->parent_id === null) {
                    if ($it->label === 'Home') {
                        $homeNode = &$byId[$it->id];
                    } else if ($it->label === 'Profile') {
                        $profileNode = &$byId[$it->id];
                    } else {
                        $tree[] = &$byId[$it->id];
                    }
                } else {
                    if (isset($byId[$it->parent_id])) {
                        $byId[$it->parent_id]['children'][] = &$byId[$it->id];
                    }
                }
            }

            $finalTree = [];
            if ($homeNode) {
                $finalTree[] = $homeNode;
            }
            $finalTree = array_merge($finalTree, $tree);
            if ($profileNode) {
                $finalTree[] = $profileNode;
            }

            return $this->ok(['id' => $menu->id, 'key' => $menu->key, 'items' => $finalTree], 'Menu loaded', ['guest' => !$user]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat menu', 500, 'SERVER_ERROR');
        }
    }
}
