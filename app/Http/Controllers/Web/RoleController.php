<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index()
    {
        return view('core.roles.index');
    }

    public function getList(Request $request)
    {
        $roles = $this->roleService->getListData($request);

        return response()->json([
            'data' => $roles
        ]);
    }
}
