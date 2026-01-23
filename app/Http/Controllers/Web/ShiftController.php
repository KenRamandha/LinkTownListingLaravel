<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance\Shift;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function index()
    {
        return view('core.shift.index');
    }

    public function getList(Request $request)
    {
        $query = Shift::query()
            ->join('companies', 'shifts.company_id', '=', 'companies.id')
            ->select('shifts.*', 'companies.name as company_name');

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('shifts.name', 'like', "%{$search}%")
                    ->orWhere('companies.name', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    public function add()
    {
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();
        return view('core.shift.add', compact('companies'));
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
            DB::beginTransaction();

            $companyId = $request->company_id;
            $shiftName = str_replace(' ', '', $request->name);

            $prefix = $companyId . '-' . $shiftName . '-';

            $lastShift = Shift::where('id', 'like', $prefix . '%')
                ->orderBy('id', 'desc')
                ->first();

            $counter = 0;
            if ($lastShift) {
                $lastId = $lastShift->id;
                $numberPart = substr($lastId, strrpos($lastId, '-') + 1);
                if (is_numeric($numberPart)) {
                    $counter = (int) $numberPart;
                }
            }

            $counter++;
            $shiftId = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);

            Shift::create([
                'id' => $shiftId,
                'company_id' => $companyId,
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            DB::commit();
            return response()->json(['message' => 'Shift created successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create shift: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $shift = Shift::where('id', $id)->firstOrFail();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();

        return view('core.shift.add', compact('shift', 'companies'));
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
            $shift = Shift::where('id', $id)->firstOrFail();

            $shift->update([
                'company_id' => $request->company_id,
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            return response()->json(['message' => 'Shift updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update shift: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $shift = Shift::where('id', $id)->firstOrFail();
            $shift->delete();
            return response()->json(['message' => 'Shift deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete shift: ' . $e->getMessage()], 500);
        }
    }
}
