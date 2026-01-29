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
}
