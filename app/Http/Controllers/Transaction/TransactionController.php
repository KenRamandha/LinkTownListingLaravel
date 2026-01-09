<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction\TrDailyH;
use App\Models\Transaction\TrDailyD;
use App\Models\Transaction\MappingProduk;
use App\Models\Transaction\MappingBarcode;
use App\Models\Core\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TransactionController extends Controller
{
    // GET /api/transactions - Menampilkan daftar transaksi dengan filter status, tanggal, dan pagination
    public function index(Request $request)
    {
        try {
            // Determine filtering based on status parameter
            $hasStatusFilter = $request->has('status') && $request->status;
            $showDeleted = $hasStatusFilter && $request->status === 'deleted';
            $showNonDeleted = $hasStatusFilter && $request->status !== 'deleted';
            
            $query = TrDailyH::with(['details' => function($query) use ($hasStatusFilter, $showNonDeleted) {
                // Only filter details by deleted_date if filtering by non-deleted status
                if ($showNonDeleted) {
                    $query->whereNull('deleted_date');
                }
            }]);
            
            // Filter by deleted status only if status parameter is provided
            if ($hasStatusFilter) {
                if ($showDeleted) {
                    $query->whereNotNull('deleted_date');
                } else {
                    $query->whereNull('deleted_date');
                }
                
                // Filter by Status
                $query->where('status', $request->status);
            }
            // If no status filter, show all transactions (no deleted_date filter)

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
                    'image_url' => $this->publicUrl($transaction->url),
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

    // GET /api/transactions/{id} - Menampilkan detail transaksi berdasarkan ID
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
                'updated_at' => $transaction->updated_date,
                'image_url' => $this->publicUrl($transaction->url),
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

    // POST /api/transactions - Membuat transaksi baru dengan items dan foto (optional)
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
            'items.*.barcode' => 'nullable|array',
            'items.*.barcode.*' => 'string',
            'image' => 'nullable|image|max:2048',
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
            $userProfile = UserProfile::where('user_id', $userId)->first();
            $userName = $userProfile->name ?? null;

            // Calculate total price
            $totalPrice = 0;
            foreach ($request->items as $item) {
                $totalPrice += ($item['quantity'] * $item['price']);
            }

            // Upload foto ke storage/app/public/transaction/{daily_id}/, simpan path di kolom url
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('transaction/' . $transactionNumber, $filename, 'public');
                $imageUrl = 'transaction/' . $transactionNumber . '/' . $filename;
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
                'url' => $imageUrl,
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
                    'barcode' => $item['barcode'] ?? null,
                    'created_date' => now(),
                    'created_by' => $userId,
                    'updated_date' => now(),
                    'updated_by' => $userId,
                ]);

                // Set barcode flag to 1 for each barcode in array
                if (isset($item['barcode']) && is_array($item['barcode'])) {
                    foreach ($item['barcode'] as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '1', 'updated_flag' => now()]);
                    }
                }
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
                'updated_at' => $transaction->updated_date,
                'image_url' => $this->publicUrl($transaction->url),
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

    // PUT /api/transactions/{id} - Update transaksi, handle items dan foto baru
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
            'items.*.barcode' => 'nullable|array',
            'items.*.barcode.*' => 'string',
            'image' => 'nullable|image|max:2048',
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

            // Upload foto baru dan hapus foto lama jika ada
            if ($request->hasFile('image')) {
                if ($transaction->url && Storage::disk('public')->exists($transaction->url)) {
                    Storage::disk('public')->delete($transaction->url);
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('transaction/' . $transaction->daily_id, $filename, 'public');
                $transaction->url = 'transaction/' . $transaction->daily_id . '/' . $filename;
            }

            // Update transaction header (without total_price first)
            $transaction->update([
                'transaction_date' => $request->transaction_date,
                'transaction_note' => $request->information,
                'description' => $request->description,
                'status' => $request->status ?? $transaction->status,
                'updated_date' => now(),
                'updated_by' => $userId,
            ]);

            // Get kode_produk from request items
            $requestKodeProduk = collect($request->items)->pluck('item_code')->toArray();

            // Soft delete items that are NOT in the request (removed items)
            TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->whereNotIn('kode_produk', $requestKodeProduk)
                ->update([
                    'deleted_date' => now(),
                    'deleted_by' => $userId,
                ]);

            // Get all items that will be soft deleted and reset their barcode flags
            $itemsToDelete = TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->whereNotIn('kode_produk', $requestKodeProduk)
                ->get();

            foreach ($itemsToDelete as $deletedItem) {
                // Reset barcode flags to 0 for deleted items
                if ($deletedItem->barcode && is_array($deletedItem->barcode)) {
                    foreach ($deletedItem->barcode as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '0', 'updated_flag' => now()]);
                    }
                }
            }

            // Soft delete items that are NOT in the request (removed items)
            TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->whereNotIn('kode_produk', $requestKodeProduk)
                ->update([
                    'deleted_date' => now(),
                    'deleted_by' => $userId,
                ]);

            // Update or create items based on kode_produk
            foreach ($request->items as $item) {
                // Check if item with same kode_produk already exists for this transaction
                $existingItem = TrDailyD::where('daily_id', $transaction->daily_id)
                    ->where('kode_produk', $item['item_code'])
                    ->whereNull('deleted_date')
                    ->first();

                $newBarcodes = $item['barcode'] ?? [];
                $newBarcodes = is_array($newBarcodes) ? $newBarcodes : [];

                if ($existingItem) {
                    // Get old barcodes to compare
                    $oldBarcodes = $existingItem->barcode ?? [];
                    $oldBarcodes = is_array($oldBarcodes) ? $oldBarcodes : [];

                    // Reset flag for removed barcodes
                    $barcodesToRemove = array_diff($oldBarcodes, $newBarcodes);
                    foreach ($barcodesToRemove as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '0', 'updated_flag' => now()]);
                    }

                    // Set flag for new barcodes
                    $barcodesToAdd = array_diff($newBarcodes, $oldBarcodes);
                    foreach ($barcodesToAdd as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '1', 'updated_flag' => now()]);
                    }

                    // Update existing item
                    $existingItem->update([
                        'type' => $item['type'] ?? 'manual',
                        'nama_produk' => $item['item_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'note_detail' => $item['note'] ?? null,
                        'barcode' => $newBarcodes ?: null,
                        'updated_date' => now(),
                        'updated_by' => $userId,
                    ]);
                } else {
                    // Set flag = 1 for all barcodes of new item
                    foreach ($newBarcodes as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '1', 'updated_flag' => now()]);
                    }

                    // Create new item
                    TrDailyD::create([
                        'daily_id' => $transaction->daily_id,
                        'user_id' => $userId,
                        'type' => $item['type'] ?? 'manual',
                        'kode_produk' => $item['item_code'],
                        'nama_produk' => $item['item_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'note_detail' => $item['note'] ?? null,
                        'barcode' => $newBarcodes ?: null,
                        'created_date' => now(),
                        'created_by' => $userId,
                        'updated_date' => now(),
                        'updated_by' => $userId,
                    ]);
                }
            }

            // Recalculate total_price from database (only non-deleted items)
            $totalPrice = TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->get()
                ->sum(function($item) {
                    return $item->quantity * $item->price;
                });

            // Update total_price
            $transaction->update([
                'total_price' => $totalPrice,
                'updated_date' => now(),
                'updated_by' => $userId,
            ]);

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
                'updated_at' => $transaction->updated_date,
                'image_url' => $this->publicUrl($transaction->url),
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

    // DELETE /api/transactions/{id} - Soft delete transaksi dan reset flag barcode
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
                'status' => 'deleted',
                'deleted_date' => now(),
                'deleted_by' => $userId,
            ]);

            // Get all items with barcodes and reset their flags
            $items = TrDailyD::where('daily_id', $transaction->daily_id)
                ->whereNull('deleted_date')
                ->get();

            foreach ($items as $item) {
                if ($item->barcode && is_array($item->barcode)) {
                    // Reset barcode flags to 0 for each barcode in array
                    foreach ($item->barcode as $barcode) {
                        MappingBarcode::where('kode_barcode', $barcode)
                            ->update(['flag' => '0', 'updated_flag' => now()]);
                    }
                }
            }

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

    // GET /api/items/search?q={query} - Cari item berdasarkan kode atau nama produk
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

    // GET /api/items/barcode/{barcode} - Ambil detail produk dari barcode (validasi flag)
    public function getItemByBarcode($barcode)
    {
        try {
            // Find barcode mapping
            $barcodeMapping = MappingBarcode::where('kode_barcode', $barcode)
                ->first();

            if (!$barcodeMapping) {
                return response()->json([
                    'message' => 'Barcode tidak ditemukan'
                ], 404);
            }

            // Check if barcode is already in use
            if ($barcodeMapping->flag == '1') {
                return response()->json([
                    'message' => 'Barcode sudah digunakan'
                ], 409);
            }

            // Get product details
            $item = MappingProduk::where('kode_produk', $barcodeMapping->kode_barang)
                ->first();

            if (!$item) {
                return response()->json([
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            // Note: Flag will be set to 1 when transaction is saved, not here
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

    // Generate nomor transaksi unik format YYYYMMDDXXXX (contoh: 202401090001)
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

    // Helper untuk generate URL publik dari path storage
    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        return asset('storage/' . $path);
    }
}
