<?php


namespace App\Http\Controllers\Menus;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\MenuCache;
use Throwable;

class MenuController extends Controller
{
    public function showPublicAware(Request $r, string $key)
    {
        try {
            $user = $r->user();
            $companyId = $user->company_id ?? 'CMP-LT';

            $cached = MenuCache::remember($companyId, $key, function () use ($companyId, $key) {
                $menuRow = DB::table('menus')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->first();

                if (!$menuRow) {
                    return null;
                }

                $items = DB::table('menu_items')
                    ->where('menu_id', $menuRow->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'parent_id' => $row->parent_id,
                        'type' => $row->type,
                        'label' => $row->label,
                        'icon' => $row->icon,
                        'route' => $row->route,
                        'permission_key' => $row->permission_key,
                    ])
                    ->all();

                return [
                    'menu' => [
                        'id' => $menuRow->id,
                        'key' => $menuRow->key,
                    ],
                    'items' => $items,
                ];
            });

            if (!$cached) {
                return $this->fail('Menu tidak ditemukan', 404, 'NOT_FOUND');
            }

            $menu = (object) $cached['menu'];
            $items = $cached['items'];

            $visibleForGuestRoot = ['Home', 'Profile'];
            $permissions = $user ? $user->effectivePermissions() : [];

            $rendered = MenuCache::rememberRendered(
                $companyId,
                $user?->id,
                $key,
                function () use ($user, $items, $menu, $visibleForGuestRoot, $permissions) {
                    $allowed = $user ? array_flip($permissions) : [];
                    $tree = [];
                    $byId = [];
                    $homeNode = null;
                    $profileNode = null;

                    foreach ($items as $it) {
                        $visible = true;

                        $permissionKey = $it['permission_key'] ?? null;
                        if ($user) {
                            if ($permissionKey && !isset($allowed[$permissionKey])) {
                                $visible = false;
                            }
                        } else {
                            $isRoot = ($it['parent_id'] ?? null) === null;
                            $visible = $isRoot && in_array($it['label'] ?? '', $visibleForGuestRoot, true);
                        }

                        if (!$visible) continue;

                        $node = [
                            'id' => $it['id'],
                            'type' => $it['type'],
                            'label' => $it['label'],
                            'icon' => $it['icon'],
                            'route' => $it['route'],
                            'children' => []
                        ];
                        $byId[$it['id']] = $node;

                        if (($it['parent_id'] ?? null) === null) {
                            if ($it['label'] === 'Home') {
                                $homeNode = &$byId[$it['id']];
                            } else if ($it['label'] === 'Profile') {
                                $profileNode = &$byId[$it['id']];
                            } else {
                                $tree[] = &$byId[$it['id']];
                            }
                        } else {
                            $parentId = $it['parent_id'];
                            if (isset($byId[$parentId])) {
                                $byId[$parentId]['children'][] = &$byId[$it['id']];
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

                    return [
                        'menu_id' => $menu->id,
                        'menu_key' => $menu->key,
                        'items' => $finalTree,
                        'guest' => !$user,
                    ];
                }
            );

            return $this->ok(
                [
                    'id' => $rendered['menu_id'],
                    'key' => $rendered['menu_key'],
                    'items' => $rendered['items'],
                ],
                'Menu loaded',
                ['guest' => $rendered['guest']]
            );
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat menu', 500, 'SERVER_ERROR');
        }
    }
}
