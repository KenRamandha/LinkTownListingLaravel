<?php

namespace App\Services;

use App\Models\Core\Role;
use Illuminate\Http\Request;

class RoleService
{
    /**
     * Get role list for DataTables
     */
    public function getListData(Request $request)
    {
        $query = Role::query()->select(['id', 'name', 'slug', 'description', 'created_at']);

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order.0.column')) {
            $columns = ['name', 'slug', 'description', 'created_at'];
            $columnIndex = $request->input('order.0.column');
            $direction = $request->input('order.0.dir');
            if (isset($columns[$columnIndex])) {
                $query->orderBy($columns[$columnIndex], $direction);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->get();
    }
}
