<?php

namespace App\Services;

use App\Models\Attendance\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ShiftService
{
    /**
     * Get shift list for DataTables
     */
    public function getListData(Request $request)
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

        return $query->get();
    }

    /**
     * Create a new shift
     */
    public function createShift(array $data)
    {
        DB::beginTransaction();
        try {
            $companyId = $data['company_id'];
            $shiftName = str_replace(' ', '', $data['name']);
            $prefix = $companyId . '-' . $shiftName . '-';

            $lastShift = Shift::where('id', 'like', $prefix . '%')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
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

            $shift = Shift::create([
                'id' => $shiftId,
                'company_id' => $companyId,
                'name' => $data['name'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);

            DB::commit();
            return $shift;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing shift
     */
    public function updateShift($id, array $data)
    {
        $shift = Shift::where('id', $id)->firstOrFail();

        $shift->update([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);

        return $shift;
    }

    /**
     * Get data for adding a shift
     */
    public function getAddData()
    {
        return [
            'companies' => DB::table('companies')->select('id', 'name', 'code')->get()
        ];
    }

    /**
     * Get data for editing a shift
     */
    public function getEditData($id)
    {
        $shift = Shift::where('id', $id)->firstOrFail();
        $companies = DB::table('companies')->select('id', 'name', 'code')->get();

        return compact('shift', 'companies');
    }

    /**
     * Delete a shift
     */
    public function deleteShift($id)
    {
        $shift = Shift::where('id', $id)->firstOrFail();
        return $shift->delete();
    }
}
