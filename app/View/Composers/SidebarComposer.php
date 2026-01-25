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

        $companyId = $user->company_id;

        $mainMenus = DB::table('config_main_menu as cmm')
            ->join('config_main_menu_permission as cmmp', 'cmmp.main_menu_id', '=', 'cmm.id')
            ->where('cmmp.user_id', $user->id)
            ->whereJsonContains('cmmp.company_ids', $companyId)
            ->where('cmmp.can_view', 1)
            ->where('cmm.is_active', 1)
            ->select(
                'cmm.id',
                'cmm.menu_name',
                'cmm.menu_icon',
                'cmm.menu_route',
                'cmm.menu_order'
            )
            ->distinct()
            ->orderBy('cmm.menu_order')
            ->get();

        $subMenus = DB::table('config_sub_menu as csm')
            ->join('config_sub_menu_permission as csmp', 'csmp.sub_menu_id', '=', 'csm.id')
            ->where('csmp.user_id', $user->id)
            ->whereJsonContains('csmp.company_ids', $companyId)
            ->where('csmp.can_view', 1)
            ->where('csm.is_active', 1)
            ->select(
                'csm.id',
                'csm.main_menu_id',
                'csm.menu_name',
                'csm.menu_route',
                'csm.menu_order'
            )
            ->distinct()
            ->orderBy('csm.menu_order')
            ->get();

        $currentRoute = Route::currentRouteName();
        $mainMenuMap = [];

        foreach ($mainMenus as $mm) {
            $normalizedRoute = $mm->menu_route ? str_replace(':', '.', $mm->menu_route) : null;
            $mainMenuMap[$mm->id] = [
                'id' => $mm->id,
                'name' => $mm->menu_name,
                'icon' => $mm->menu_icon,
                'route' => $normalizedRoute,
                'is_active' => ($normalizedRoute === $currentRoute),
                'has_active_child' => false,
                'children' => []
            ];
        }

        foreach ($subMenus as $sm) {
            if (isset($mainMenuMap[$sm->main_menu_id])) {
                $normalizedRoute = $sm->menu_route ? str_replace(':', '.', $sm->menu_route) : null;
                $isActive = ($normalizedRoute === $currentRoute);
                if ($isActive) {
                    $mainMenuMap[$sm->main_menu_id]['has_active_child'] = true;
                }

                $mainMenuMap[$sm->main_menu_id]['children'][] = [
                    'id' => $sm->id,
                    'name' => $sm->menu_name,
                    'route' => $normalizedRoute,
                    'is_active' => $isActive
                ];
            }
        }

        $view->with('sidebarMenu', array_values($mainMenuMap));
    }
}