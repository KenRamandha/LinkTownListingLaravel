<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        return view('core.users.index');
    }

    public function getList(Request $request)
    {
        $users = $this->userService->getListData($request);

        return response()->json([
            'data' => $users
        ]);
    }

    public function add()
    {
        $data = $this->userService->getAddData();
        return view('core.users.add', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'name' => 'required|string|max:120',
            'company_id' => 'required',
            'status' => 'required',
            'join_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'tanggal_lahir' => 'nullable|date',
            'gender' => 'nullable|in:PRIA,WANITA',
        ]);

        try {
            $result = $this->userService->createUser($request->all(), $request);

            return response()->json([
                'message' => 'User created successfully',
                'user_id' => $result['user_id'],
                'employee_code' => $result['employee_code']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $data = $this->userService->getEditData($id);

        if (!$data) {
            return abort(404);
        }

        return view('core.users.add', $data);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'name' => 'required|string|max:120',
            'company_id' => 'required',
            'status' => 'required',
            'join_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'tanggal_lahir' => 'nullable|date',
            'gender' => 'nullable|in:PRIA,WANITA',
        ]);

        try {
            $this->userService->updateUser($id, $request->all(), $request);
            return response()->json(['message' => 'User updated successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->userService->deleteUser($id);
            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }

    public function getMappings(Request $request, $userId)
    {
        $mappings = $this->userService->getShiftMappings($userId);
        return response()->json(['data' => $mappings]);
    }

    public function storeMapping(Request $request, $userId)
    {
        $request->validate([
            'shift_id' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $this->userService->storeShiftMapping($userId, $request->all());
            return response()->json(['message' => 'Shift mapping saved successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to save mapping: ' . $e->getMessage()], 500);
        }
    }

    public function destroyMapping($id)
    {
        try {
            DB::table('shifts_mapping')->where('id', $id)->delete();
            return response()->json(['message' => 'Mapping deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete mapping'], 500);
        }
    }

    public function getDepartmentsByCompany($company_id)
    {
        $departments = DB::table('departments')
            ->where('company_id', $company_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function getAttachments($userId)
    {
        $attachments = $this->userService->getAttachments($userId);
        return response()->json($attachments);
    }

    public function storeAttachment(Request $request, $userId)
    {
        $request->validate([
            'doc_type' => 'required',
            'file' => 'required|file|max:10240',
        ]);

        if ($this->userService->storeAttachment($userId, $request)) {
            return response()->json(['message' => 'Attachment uploaded successfully']);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function destroyAttachment($id)
    {
        if ($this->userService->deleteAttachment($id)) {
            return response()->json(['message' => 'Attachment deleted successfully']);
        }

        return response()->json(['message' => 'Attachment not found'], 404);
    }
}