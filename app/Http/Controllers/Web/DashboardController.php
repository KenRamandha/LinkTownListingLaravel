<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $userActivities = $this->dashboardService->getUserActivities($request->user());
            return view('partials.activity-cards', compact('userActivities'))->render();
        }

        $stats = $this->dashboardService->getStats($request->user());
        $users = $this->dashboardService->getAllUsers($request->user());

        return view('dashboard', array_merge($stats, ['users' => $users]));
    }

    public function getAttendanceData(Request $request)
    {
        $userIds = $request->input('user_ids');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $data = $this->dashboardService->getAttendanceList($request->user(), $userIds, $startDate, $endDate);

        return response()->json(['data' => $data]);
    }

    public function getVisitData(Request $request)
    {
        $userIds = $request->input('user_ids');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $data = $this->dashboardService->getVisitList($request->user(), $userIds, $startDate, $endDate);

        return response()->json(['data' => $data]);
    }

    public function exportAttendance(Request $request)
    {
        $userIds = $request->input('user_ids');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $fileName = 'attendance_export_' . date('Y-m-d_H-i-s') . '.csv';

        $data = $this->dashboardService->getAttendanceList($request->user(), $userIds, $startDate, $endDate);

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['User Name', 'Date', 'In', 'Out', 'In Address', 'Out Address']);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->name,
                    $row->work_date,
                    $row->checkin_time,
                    $row->checkout_time,
                    $row->checkin_address,
                    $row->checkout_address,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportVisit(Request $request)
    {
        $userIds = $request->input('user_ids');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $fileName = 'visit_export_' . date('Y-m-d_H-i-s') . '.csv';

        $data = $this->dashboardService->getVisitList($request->user(), $userIds, $startDate, $endDate);

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['User Name', 'Date', 'Visit In', 'Visit Out', 'Address In', 'Address Out', 'Keterangan']);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->name,
                    $row->tanggal,
                    $row->visit_in,
                    $row->visit_out,
                    $row->address_in,
                    $row->address_out,
                    $row->keterangan_in,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
