<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('core.users.index');
    }

    public function getList(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('users.company_id', $companyId)
            ->select([
                'users.id',
                'users.email',
                'users.status',
                'users.is_employee',
                'users.created_at',
                'users.last_login_at',
                'user_profiles.name',
                'user_profiles.position',
                'roles.name as role_name'
            ]);

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('user_profiles.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('user_profiles.position', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order.0.column')) {
            $columns = ['user_profiles.name', 'user_profiles.position', 'users.status', 'users.created_at'];
            $columnIndex = $request->input('order.0.column');
            $direction = $request->input('order.0.dir');
            if (isset($columns[$columnIndex])) {
                $query->orderBy($columns[$columnIndex], $direction);
            }
        } else {
            $query->orderBy('user_profiles.name', 'asc');
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    public function add()
    {
        $menus = $this->getMenuTree();
        $roles = DB::table('roles')->select('id', 'name')->get();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();

        return view('core.users.add', compact('menus', 'roles', 'companies'));
    }

    public function getDepartementsByCompany($company_id)
    {
        $departements = DB::table('departments')
            ->where('company_id', $company_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($departements);
    }

    private function getMenuTree()
    {
        $user = auth()->user();
        if (!$user)
            return [];

        $menus = DB::table('menus')
            ->where('company_id', $user->company_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        foreach ($menus as $menu) {
            $items = DB::table('menu_items')
                ->where('menu_id', $menu->id)
                ->orderBy('sort_order')
                ->get();
            $byId = [];
            foreach ($items as $item) {
                $byId[$item->id] = (array) $item;
                $byId[$item->id]['children'] = [];
            }

            $tree = [];
            foreach ($byId as $id => &$node) {
                if (empty($node['parent_id'])) {
                    $tree[] = &$node;
                } else {
                    if (isset($byId[$node['parent_id']])) {
                        $byId[$node['parent_id']]['children'][] = &$node;
                    }
                }
            }

            $menu->tree = $tree;
        }

        return $menus;
    }

}