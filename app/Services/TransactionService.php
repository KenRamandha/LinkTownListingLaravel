<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionService
{
    /**
     * Get authorized company IDs for a user
     */
    public function getAuthorizedCompanyIds($user)
    {
        $permissionRow = DB::table('config_sub_menu_permission')
            ->where('user_id', $user->id)
            ->where('can_view', 1)
            ->first();

        $companyIds = [];
        if ($permissionRow) {
            $companyIds = $permissionRow->company_ids;
            if (is_string($companyIds)) {
                $companyIds = json_decode($companyIds, true) ?: [];
            }
        }

        if (empty($companyIds)) {
            $permissionRow = DB::table('config_main_menu_permission')
                ->where('user_id', $user->id)
                ->where('can_view', 1)
                ->first();

            if ($permissionRow) {
                $companyIds = $permissionRow->company_ids;
                if (is_string($companyIds)) {
                    $companyIds = json_decode($companyIds, true) ?: [];
                }
            }
        }

        if (empty($companyIds)) {
            $companyIds = [$user->company_id];
        }

        return $companyIds;
    }

    /**
     * Get index data (companies authorized for user)
     */
    public function getIndexData($user)
    {
        $companyIds = $this->getAuthorizedCompanyIds($user);

        return [
            'companies' => DB::table('companies')
                ->whereIn('id', $companyIds)
                ->select('id', 'name')
                ->get()
        ];
    }

    /**
     * Get daily transaction list
     */
    public function getDailyTransactions(Request $request)
    {
        $type = $request->input('type', 'daily');
        $companyId = $request->input('company_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = DB::table('tr_daily_h')
            ->leftJoin('users', 'tr_daily_h.user_id', '=', 'users.id')
            ->leftJoin('companies', 'users.company_id', '=', 'companies.id')
            ->where('users.company_id', $companyId)
            ->whereNull('tr_daily_h.deleted_date')
            ->select([
                'tr_daily_h.*',
                'companies.name as company_name'
            ]);

        if ($type === 'daily') {
            $query->whereDate('tr_daily_h.transaction_date', Carbon::today());
        } else {
            if ($startDate && $endDate) {
                $query->whereBetween('tr_daily_h.transaction_date', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            } else {
                $query->whereMonth('tr_daily_h.transaction_date', Carbon::now()->month)
                    ->whereYear('tr_daily_h.transaction_date', Carbon::now()->year);
            }
        }

        return $query->orderBy('tr_daily_h.created_at', 'desc')->get();
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails($dailyId)
    {
        return DB::table('tr_daily_d')
            ->where('daily_id', $dailyId)
            ->where('deleted_by', null)
            ->get();
    }
}
