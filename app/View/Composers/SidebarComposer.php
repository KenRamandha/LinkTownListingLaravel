<?php

namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class SidebarComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        if (!$user) {
            $view->with('sidebarMenu', []);
            return;
        }

        $menus = DB::table('user_roles as ur')
            ->join('config_permission as cp', 'cp.role_id', '=', 'ur.role_id')
            ->join('config_menu as cm', 'cm.id', '=', 'cp.menu_id')
            ->where('ur.user_id', $user->id)
            ->where('cp.can_view', 1)
            ->where('cm.is_active', 1)
            ->select(
                'cm.id',
                'cm.parent_id',
                'cm.menu_name',
                'cm.menu_icon',
                'cm.menu_route',
                'cm.menu_order'
            )
            ->distinct()
            ->orderBy('cm.parent_id')
            ->orderBy('cm.menu_order')
            ->get();

        $byId = [];
        $currentRoute = Route::currentRouteName();

        foreach ($menus as $menu) {
            $byId[$menu->id] = [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'name' => $menu->menu_name,
                'icon' => $menu->menu_icon,
                'route' => $menu->menu_route,
                'is_active' => ($menu->menu_route === $currentRoute),
                'children' => []
            ];
        }

        $tree = [];
        foreach ($byId as $id => &$node) {
            if ($node['is_active']) {
                $parentId = $node['parent_id'];
                while ($parentId && isset($byId[$parentId])) {
                    $byId[$parentId]['has_active_child'] = true;
                    $parentId = $byId[$parentId]['parent_id'];
                }
            }

            if (empty($node['parent_id'])) {
                $tree[] = &$node;
            } else {
                if (isset($byId[$node['parent_id']])) {
                    $byId[$node['parent_id']]['children'][] = &$node;
                }
            }
        }

        $view->with('sidebarMenu', $tree);
    }
}