<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * @var TransactionService
     */
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Get statistics for the dashboard
     * 
     * @param \App\Models\Core\User $user
     * @return array
     */
    public function getStats($user)
    {
        $companyIds = $this->transactionService->getAuthorizedCompanyIds($user);

        $totalUser = DB::table('users')
            ->whereIn('company_id', $companyIds)
            ->count();

        $totalTransaksi = DB::table('tr_daily_h')
            ->join('users', function ($join) {
                $join->on(DB::raw('tr_daily_h.user_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('users.id COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('users.company_id', $companyIds)
            ->whereDate('tr_daily_h.transaction_date', Carbon::today())
            ->whereNull('tr_daily_h.deleted_date')
            ->count();

        $totalAbsensi = DB::table('shifts_mapping')
            ->join('users', function ($join) {
                $join->on(DB::raw('shifts_mapping.user_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('users.id COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('users.company_id', $companyIds)
            ->whereDate('shifts_mapping.work_date', Carbon::today())
            ->where(function ($query) {
                $query->whereNotNull('shifts_mapping.checkin_time')
                    ->orWhereNotNull('shifts_mapping.checkout_time');
            })
            ->count();

        $totalVisit = DB::table('kunjungan')
            ->join('users', function ($join) {
                $join->on(DB::raw('kunjungan.user_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('users.id COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('users.company_id', $companyIds)
            ->whereDate('kunjungan.tanggal', Carbon::today())
            ->count();

        return [
            'totalUser' => $totalUser,
            'totalTransaksi' => $totalTransaksi,
            'totalAbsensi' => $totalAbsensi,
            'totalVisit' => $totalVisit,
            'userActivities' => $this->getUserActivities($user),
        ];
    }

    /**
     * Get paginated user activities
     * 
     * @param \App\Models\Core\User $user
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUserActivities($user)
    {
        $companyIds = $this->transactionService->getAuthorizedCompanyIds($user);

        return DB::table('users')
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->leftJoin('shifts_mapping', function ($join) {
                $join->on(DB::raw('users.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('shifts_mapping.user_id COLLATE utf8mb4_unicode_ci'))
                    ->whereDate('shifts_mapping.work_date', Carbon::today());
            })
            ->leftJoin(DB::raw("(SELECT 
                user_id, 
                COUNT(id) as total_visit, 
                MAX(visit_in) as last_visit_time 
            FROM kunjungan 
            WHERE tanggal = '" . Carbon::today()->toDateString() . "' 
            GROUP BY user_id) as v"), function ($join) {
                $join->on(DB::raw('users.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.user_id COLLATE utf8mb4_unicode_ci'));
            })
            ->whereIn('users.company_id', $companyIds)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('shifts_mapping.checkin_time')
                        ->orWhereNotNull('shifts_mapping.checkout_time');
                })
                    ->orWhereNotNull('v.user_id');
            })
            ->select([
                'users.id',
                'user_profiles.name',
                'user_profiles.avatar_url',
                'shifts_mapping.checkin_time',
                'shifts_mapping.checkout_time',
                'v.total_visit',
                'v.last_visit_time'
            ])
            ->orderByRaw('GREATEST(COALESCE(shifts_mapping.checkin_time, "00:00:00"), COALESCE(shifts_mapping.checkout_time, "00:00:00"), COALESCE(TIME(v.last_visit_time), "00:00:00")) DESC')
            ->paginate(8);
    }

    public function getAllUsers($currentUser)
    {
        $companyIds = $this->transactionService->getAuthorizedCompanyIds($currentUser);
        return DB::table('users')
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->whereIn('users.company_id', $companyIds)
            ->select('users.id', 'user_profiles.name')
            ->orderBy('user_profiles.name')
            ->get();
    }

    public function getAttendanceList($currentUser, $userIds, $startDate, $endDate)
    {
        $companyIds = $this->transactionService->getAuthorizedCompanyIds($currentUser);
        $userIds = (array) $userIds;

        $query = DB::table('shifts_mapping')
            ->join('users', function ($join) {
                $join->on(DB::raw('shifts_mapping.user_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('users.id COLLATE utf8mb4_unicode_ci'));
            })
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->whereIn('users.company_id', $companyIds)
            ->whereBetween('shifts_mapping.work_date', [$startDate, $endDate]);

        if (!in_array('all', $userIds) && !empty($userIds)) {
            $query->whereIn('shifts_mapping.user_id', $userIds);
        }

        return $query->select([
            'user_profiles.name',
            'shifts_mapping.work_date',
            'shifts_mapping.checkin_time',
            'shifts_mapping.checkout_time',
            'shifts_mapping.checkin_address',
            'shifts_mapping.checkout_address',
        ])
            ->orderBy('shifts_mapping.work_date', 'desc')
            ->get();
    }

    public function getVisitList($currentUser, $userIds, $startDate, $endDate)
    {
        $companyIds = $this->transactionService->getAuthorizedCompanyIds($currentUser);
        $userIds = (array) $userIds;

        $query = DB::table('kunjungan')
            ->join('users', function ($join) {
                $join->on(DB::raw('kunjungan.user_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('users.id COLLATE utf8mb4_unicode_ci'));
            })
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->whereIn('users.company_id', $companyIds)
            ->whereBetween('kunjungan.tanggal', [$startDate, $endDate]);

        if (!in_array('all', $userIds) && !empty($userIds)) {
            $query->whereIn('kunjungan.user_id', $userIds);
        }

        return $query->select([
            'user_profiles.name',
            'kunjungan.tanggal',
            'kunjungan.visit_in',
            'kunjungan.visit_out',
            'kunjungan.address_in',
            'kunjungan.address_out',
            'kunjungan.keterangan_in'
        ])
            ->orderBy('kunjungan.tanggal', 'desc')
            ->get();
    }
}
