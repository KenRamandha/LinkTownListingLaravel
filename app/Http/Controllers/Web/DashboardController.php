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

        return view('dashboard', $stats);
    }
}
