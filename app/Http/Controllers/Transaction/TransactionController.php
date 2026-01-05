<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction\TrDailyH;
use App\Models\Transaction\TrDailyD;
use App\Models\Transaction\MappingProduk;
use App\Models\Transaction\MappingBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Get list of all transactions
     * GET /api/transactions
     */
    public function index(Request $request)
    {
        try {
            $query = TrDailyH::with(['details' => function($query) {
                $query->whereNull('deleted_date');
            }])
            ->whereNull('deleted_date');

            // Filter by Status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by Date Range
            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('transaction_date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('transaction_date', '<=', $request->end_date);
            }

            // Default sorting
            $query->orderBy('transaction_date', 'desc')
                  ->orderBy('created_date', 'desc');

            // Pagination
            $perPage = $request->input('per_page', 10);
            $transactions = $query->paginate($perPage);

            $data = $transactions->getCollection()->map(function($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->daily_id,
                    'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null,
                    'information' => $transaction->transaction_note,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                    'total_amount' => $transaction->total_price,
                    'items' => $transaction->details->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_code' => $item->kode_produk,
                            'item_name' => $item->nama_produk,
                            'quantity' => $item->quantity,
                            'price' => $item->price ? (float) $item->price : 0,
                            'note' => $item->note_detail,
                            'type' => $item->type,
                        ];
                    }),
                    'created_at' => $transaction->created_date,
                    'updated_at' => $transaction->updated_date,
                ];
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single transaction by ID
     * GET /api/transactions/{id}
     */
    public function show($id)
    {
        try {
            $transaction = TrDailyH::with(['details' => function($query) {
                $query->whereNull('deleted_date');
            }])
            ->whereNull('deleted_date')
            ->find($id);

            if (!$transaction) {
                return response()->json([
                    'message' => 'Transaction not found'
                ], 404);
            }

            $data = [
                'id' => $transaction->id,
                'transaction_number' => $transaction->daily_id,
                'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null,
                'information' => $transaction->transaction_note,
                'description' => $transaction->description,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_price,
                'items' => $transaction->details->map(function($item) {
                    return [
                        'id' => $item->id,
                        'item_code' => $item->kode_produk,
                        'item_name' => $item->nama_produk,
                        'quantity' => $item->quantity,
                        'price' => $item->price ? (float) $item->price : 0,
                        'note' => $item->note_detail,
                        'type' => $item->type,
                    ];
                }),
                'created_at' => $transaction->created_date,
                'updated_at' => $transaction->updated_date,
            ];

            return response()->json([
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new transaction
     * POST /api/transactions
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'information' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,published,deleted',
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.note' => 'nullable|string',
            'items.*.type' => 'nullable|in:scan,manual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate transaction number: YYYYMMDDXXXX
            $transactionNumber = $this->generateTransactionNumber($request->transaction_date);

            // Get authenticated user
            $userId = Auth::id();
            $userName = Auth::user()->name ?? null;

            // Calculate total price
            $totalPrice = 0;
            foreach ($request->items as $item) {
                $totalPrice += ($item['quantity'] * $item['price']);
            }

            // Create transaction header
            $transaction = TrDailyH::create([
                'daily_id' => $transactionNumber,
                'user_id' => $userId,
                'user_name' => $userName,
                'transaction_date' => $request->transaction_date,
                'transaction_note' => $request->information,
                'description' => $request->description,
                'total_price' => $totalPrice,
                'status' => $request->status ?? 'pending',
                'created_date' => now(),
                'created_by' => $userId,
                'updated_date' => now(),
                'updated_by' => $userId,
            ]);

            // Create transaction items
            foreach ($request->items as $item) {
                TrDailyD::create([
                    'daily_id' => $transactionNumber,
                    'user_id' => $userId,
                    'type' => $item['type'] ?? 'manual',
                    'kode_produk' => $item['item_code'],
                    'nama_produk' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'note_detail' => $item['note'] ?? null,
                    'created_date' => now(),
                    'created_by' => $userId,
                    'updated_date' => now(),
                    'updated_by' => $userId,
                ]);
            }

            DB::commit();

            // Load details for response
            $transaction->load(['details' => function($query) {
                $query->whereNull('deleted_date');
            }]);

            $data = [
                'id' => $transaction->id,
                'transaction_number' => $transaction->daily_id,
                'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null,
                'information' => $transaction->transaction_note,
                'description' => $transaction->description,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_price,
                'items' => $transaction->details->map(function($item) {
                    return [
                        'id' => $item->id,
                        'item_code' => $item->kode_produk,
                        'item_name' => $item->nama_produk,
                        'quantity' => $item->quantity,
                        'price' => $item->price ? (float) $item->price : 0,
                        'note' => $item->note_detail,
                        'type' => $item->type,
                    ];
                }),
                'created_at' => $transaction->created_date,
                'updated_at' => $transaction->updated_date,
            ];

            return response()->json([
                'data' => $data
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing transaction
     * PUT /api/transactions/{id}
     */
    public function update(Request $request, $id)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'information' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,published,deleted',
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.note' => 'nullable|string',
            'items.*.type' => 'nullable|in:scan,manual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = TrDailyH::whereNull('deleted_date')->find($id);

            if (!$transaction) {
                return response()->json([
                    'message' => 'Transaction not found'
                ], 404);
            }

            DB::beginTransaction();

            $userId = Auth::id();

            // Calculate total price
            $totalPrice = 0;
            foreach ($request->items as $item) {
                $totalPrice += ($item['quantity'] * $item['price']);
            }

            // Update transaction header
            $transaction->update([
                'transaction_date' => $request->transaction_date,
                'transaction_note' => $request->information,
                'description' => $request->description,
                'total_price' => $totalPrice,
                'status' => $request->status ?? $transaction->status,
                'updated_date' => now(),
                'updated_by' => $userId,
            ]);

            // Soft delete existing items
            TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->update([
                    'deleted_date' => now(),
                    'deleted_by' => $userId,
                ]);

            // Create new items
            foreach ($request->items as $item) {
                TrDailyD::create([
                    'daily_id' => $transaction->daily_id,
                    'user_id' => $userId,
                    'type' => $item['type'] ?? 'manual',
                    'kode_produk' => $item['item_code'],
                    'nama_produk' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'note_detail' => $item['note'] ?? null,
                    'created_date' => now(),
                    'created_by' => $userId,
                    'updated_date' => now(),
                    'updated_by' => $userId,
                ]);
            }

            DB::commit();

            // Reload details for response
            $transaction->load(['details' => function($query) {
                $query->whereNull('deleted_date');
            }]);

            $data = [
                'id' => $transaction->id,
                'transaction_number' => $transaction->daily_id,
                'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null,
                'information' => $transaction->transaction_note,
                'description' => $transaction->description,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_price,
                'items' => $transaction->details->map(function($item) {
                    return [
                        'id' => $item->id,
                        'item_code' => $item->kode_produk,
                        'item_name' => $item->nama_produk,
                        'quantity' => $item->quantity,
                        'price' => $item->price ? (float) $item->price : 0,
                        'note' => $item->note_detail,
                        'type' => $item->type,
                    ];
                }),
                'created_at' => $transaction->created_date,
                'updated_at' => $transaction->updated_date,
            ];

            return response()->json([
                'data' => $data
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete transaction
     * DELETE /api/transactions/{id}
     */
    public function destroy($id)
    {
        try {
            $transaction = TrDailyH::whereNull('deleted_date')->find($id);

            if (!$transaction) {
                return response()->json([
                    'message' => 'Transaction not found'
                ], 404);
            }

            DB::beginTransaction();

            $userId = Auth::id();

            // Soft delete transaction header
            $transaction->update([
                'deleted_date' => now(),
                'deleted_by' => $userId,
            ]);

            // Soft delete all items
            TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->update([
                    'deleted_date' => now(),
                    'deleted_by' => $userId,
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaction deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search items by code or name
     * GET /api/items/search?q={query}
     */
    public function searchItems(Request $request)
    {
        try {
            $query = $request->input('q');

            if (empty($query)) {
                return response()->json([
                    'message' => 'Search query is required'
                ], 400);
            }

            // Search in mapping_produk table
            $items = MappingProduk::where('kode_produk', 'LIKE', "%{$query}%")
                ->orWhere('nama_produk', 'LIKE', "%{$query}%")
                ->orderBy('kode_produk', 'asc')
                ->limit(50)
                ->get();

            $data = $items->map(function($item) {
                return [
                    'code' => $item->kode_produk,
                    'name' => $item->nama_produk,
                ];
            });

            return response()->json([
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get item by barcode
     * GET /api/items/barcode/{barcode}
     */
    public function getItemByBarcode($barcode)
    {
        try {
            // Find barcode mapping
            $barcodeMapping = MappingBarcode::where('kode_barcode', $barcode)
                ->where('flag', '0') // Only active items
                ->first();

            if (!$barcodeMapping) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            // Get product details
            $item = MappingProduk::where('kode_produk', $barcodeMapping->kode_barang)
                ->first();

            if (!$item) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }

            $data = [
                'code' => $item->kode_produk,
                'name' => $item->nama_produk,
                'barcode' => $barcode,
            ];

            return response()->json([
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique transaction number with format YYYYMMDDXXXX
     */
    private function generateTransactionNumber($date)
    {
        $prefix = Carbon::parse($date)->format('Ymd');
        
        // Find the last transaction number for this date
        $lastNumber = TrDailyH::where('daily_id', 'LIKE', $prefix . '%')
            ->orderBy('daily_id', 'desc')
            ->value('daily_id');
        
        if ($lastNumber) {
            // Extract sequence number and increment
            $sequence = (int) substr($lastNumber, -4) + 1;
        } else {
            // First transaction for this date
            $sequence = 1;
        }
        
        // Format: YYYYMMDD + 4-digit sequence (padded with zeros)
        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
