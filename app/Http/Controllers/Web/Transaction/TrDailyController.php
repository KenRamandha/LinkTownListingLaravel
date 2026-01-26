<?php

namespace App\Http\Controllers\Web\Transaction;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrDailyController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(Request $request)
    {
        $data = $this->transactionService->getIndexData($request->user());
        return view('transaction.daily.index', $data);
    }

    public function getList(Request $request)
    {
        $transactions = $this->transactionService->getDailyTransactions($request);

        return response()->json([
            'data' => $transactions
        ]);
    }

    public function getDetails($daily_id)
    {
        $details = $this->transactionService->getTransactionDetails($daily_id);

        return response()->json([
            'data' => $details
        ]);
    }
}
