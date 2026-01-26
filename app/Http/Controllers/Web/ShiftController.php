<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance\Shift;

class ShiftController extends Controller
{
    protected $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    public function index()
    {
        return view('core.shift.index');
    }

    public function getList(Request $request)
    {
        $shifts = $this->shiftService->getListData($request);

        return response()->json([
            'data' => $shifts
        ]);
    }

    public function add()
    {
        $data = $this->shiftService->getAddData();
        return view('core.shift.add', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'name' => 'required|string|max:100',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        try {
            $this->shiftService->createShift($request->all());
            return response()->json(['message' => 'Shift created successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create shift: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $data = $this->shiftService->getEditData($id);
        return view('core.shift.add', $data);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'company_id' => 'required',
            'name' => 'required|string|max:100',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        try {
            $this->shiftService->updateShift($id, $request->all());
            return response()->json(['message' => 'Shift updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update shift: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->shiftService->deleteShift($id);
            return response()->json(['message' => 'Shift deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete shift: ' . $e->getMessage()], 500);
        }
    }
}
