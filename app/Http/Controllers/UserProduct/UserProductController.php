<?php

namespace App\Http\Controllers\UserProduct;

use App\Http\Controllers\Controller;
use App\Jobs\SyncToLamudiJob;
use App\Models\UserProduct\MsProduct;
use App\Models\UserProduct\MsProductImage;
use App\Models\UserProduct\MsProductLocation;
use App\Services\Lamudi\LamudiAdMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class UserProductController extends Controller
{
    // Generate unique product ID
    private function generateProductId(): string
    {
        $date = now()->format('Ymd');
        $lastProduct = MsProduct::where('product_id', 'like', "PRD-{$date}-%")
            ->orderBy('product_id', 'desc')
            ->first();

        if ($lastProduct) {
            $lastNumber = (int) substr($lastProduct->product_id, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return "PRD-{$date}-{$newNumber}";
    }

    // Calculate commission price from base price and percentage
    private function calculateCommission(mixed $price, mixed $percentage): ?float
    {
        if ($price === null || $percentage === null) {
            return null;
        }

        $priceFloat = (float) $price;
        $percentageInt = (int) $percentage;

        if ($priceFloat <= 0 || $percentageInt < 0 || $percentageInt > 100) {
            return null;
        }

        return ($priceFloat * $percentageInt) / 100;
    }

    // Get validation rules for product
    private function getValidationRules(bool $isUpdate = false): array
    {
        return [
            'title' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'brochure_title' => 'nullable|string|max:25',
            'description' => 'nullable|string',
            'condition' => 'nullable|string|exists:tr_product_detail,detail_id,detail_type,CONDITION',
            'product_type' => 'nullable|string|exists:tr_product_detail,detail_id,detail_type,PROPERTY_TYPE',
            'legal' => 'nullable|string|exists:tr_product_detail,detail_id,detail_type,LEGAL',
            'label' => 'nullable|array',
            'label.*' => 'string|exists:tr_product_detail,detail_id,detail_type,LABEL',
            'developer' => 'nullable|string|max:255',
            'specification' => 'nullable|json',
            'facility' => 'nullable|json',
            'province' => 'nullable|integer',
            'city' => 'nullable|integer',
            'area' => 'nullable|integer',
            'address' => 'nullable|string',
            'location' => 'nullable|array',
            'location.latitude' => 'nullable|numeric',
            'location.longitude' => 'nullable|numeric',
            'owner_name' => 'nullable|string|max:100',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|string|email|max:100',
            'owner_nik' => 'nullable|string|max:20',
            'owner_address' => 'nullable|string',
            'user_name' => 'nullable|string|max:100',
            'user_phone' => 'nullable|string|max:20',
            'listing_type' => 'nullable|string|exists:tr_product_detail,detail_id,detail_type,LISTING_TYPE',
            'selling_price' => 'nullable|numeric',
            'rental_price' => 'nullable|numeric',
            'commission_selling_percentage' => 'nullable|numeric|min:0|max:100',
            'commission_rent_percentage' => 'nullable|numeric|min:0|max:100',
            'rental_terms' => 'nullable|string|max:255',
            'status' => 'nullable|in:Draft,Publish',
            // Image validation
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'display_images' => 'nullable|array|max:10',
            'display_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'layout_images' => 'nullable|array|max:10',
            'layout_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'brochure_image' => 'nullable|file|max:10240', // Max 10MB for brochure - file type validated separately for VPS compatibility
        ];
    }

    // Custom validation for brochure image to handle VPS MIME detection issues
    private function validateBrochureImage(Request $request): ?string
    {
        if (!$request->hasFile('brochure_image')) {
            return null;
        }

        $file = $request->file('brochure_image');
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            return 'Format brosur harus: jpeg, png, jpg, webp, atau pdf';
        }

        // Check file size (10MB = 10485760 bytes)
        if ($file->getSize() > 10485760) {
            return 'Ukuran brosur maksimal 10MB';
        }

        return null;
    }

    // Get custom validation messages for product
    private function getValidationMessages(): array
    {
        return [
            'title.required' => 'Judul properti (Title) wajib diisi',
            'title.max' => 'Judul properti maksimal 255 karakter',
            'brochure_title.max' => 'Judul brosur maksimal 25 karakter',
            'description.string' => 'Deskripsi properti harus berupa teks',
            'condition.exists' => 'Kondisi properti tidak valid',
            'product_type.exists' => 'Tipe properti tidak valid',
            'legal.exists' => 'Status legal tidak valid',
            'label.*.exists' => 'Label tidak valid',
            'developer.max' => 'Nama developer maksimal 255 karakter',
            'owner_email.email' => 'Format email pemilik tidak valid. Contoh: nama@email.com',
            'owner_email.max' => 'Email pemilik maksimal 100 karakter',
            'owner_name.max' => 'Nama pemilik maksimal 100 karakter',
            'owner_phone.max' => 'Nomor telepon pemilik maksimal 20 karakter',
            'user_name.max' => 'Nama kontak maksimal 100 karakter',
            'user_phone.max' => 'Nomor telepon kontak maksimal 20 karakter',
            'listing_type.exists' => 'Tipe listing tidak valid',
            'selling_price.numeric' => 'Harga jual harus berupa angka',
            'rental_price.numeric' => 'Harga sewa harus berupa angka',
            'commission_selling_percentage.numeric' => 'Komisi jual harus berupa angka',
            'commission_selling_percentage.min' => 'Komisi jual minimal 0%',
            'commission_selling_percentage.max' => 'Komisi jual maksimal 100%',
            'commission_rent_percentage.numeric' => 'Komisi sewa harus berupa angka',
            'commission_rent_percentage.min' => 'Komisi sewa minimal 0%',
            'commission_rent_percentage.max' => 'Komisi sewa maksimal 100%',
            'location.latitude.numeric' => 'Latitude harus berupa angka (contoh: -6.2088)',
            'location.longitude.numeric' => 'Longitude harus berupa angka (contoh: 106.8456)',
            'main_image.image' => 'File utama harus berupa gambar',
            'main_image.mimes' => 'Format gambar utama harus: jpeg, png, jpg, atau webp',
            'main_image.max' => 'Ukuran gambar utama maksimal 5MB',
            'display_images.max' => 'Maksimal 10 foto tampil',
            'display_images.*.image' => 'File harus berupa gambar',
            'display_images.*.mimes' => 'Format foto harus: jpeg, png, jpg, atau webp',
            'display_images.*.max' => 'Ukuran foto maksimal 5MB',
            'layout_images.max' => 'Maksimal 10 foto denah',
            'layout_images.*.image' => 'File harus berupa gambar',
            'layout_images.*.mimes' => 'Format foto denah harus: jpeg, png, jpg, atau webp',
            'layout_images.*.max' => 'Ukuran foto denah maksimal 5MB',
            'brochure_image.max' => 'Ukuran brosur maksimal 10MB',
        ];
    }

    // Handle image uploads for a product (APPEND mode - keeps existing images)
    private function handleImages(MsProduct $product, Request $request, ?string $createdBy = null): void
    {
        Log::info('handleImages called', [
            'product_id' => $product->product_id,
            'has_main' => $request->hasFile('main_image'),
            'has_display' => $request->hasFile('display_images'),
            'has_layout' => $request->hasFile('layout_images'),
            'has_brochure' => $request->hasFile('brochure_image'),
            'display_count' => $request->hasFile('display_images') ? count($request->file('display_images')) : 0,
            'layout_count' => $request->hasFile('layout_images') ? count($request->file('layout_images')) : 0,
        ]);

        $basePath = "products/{$product->product_id}";

        // Get current max order for display images
        $maxDisplayOrder = MsProductImage::where('product_id', $product->product_id)
            ->where('image_type', 'DISPLAY')
            ->max('order') ?? 0;

        // Get current max order for layout images
        $maxLayoutOrder = MsProductImage::where('product_id', $product->product_id)
            ->where('image_type', 'LAYOUT')
            ->max('order') ?? -1;

        // Main image - replace existing main if exists
        if ($request->hasFile('main_image')) {
            // Delete existing main image first
            $existingMain = MsProductImage::where('product_id', $product->product_id)
                ->where('main', 1)
                ->first();

            if ($existingMain) {
                $this->deleteImageFile($existingMain->url);
                $existingMain->delete();
            }

            $file = $request->file('main_image');
            $filename = 'main_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($basePath, $filename, 'public');

            MsProductImage::create([
                'product_id' => $product->product_id,
                'url' => Storage::url($path),
                'image_type' => 'DISPLAY',
                'main' => 1,
                'order' => 0,
                'created_by' => $createdBy,
            ]);
        }

        // Display images - APPEND to existing
        if ($request->hasFile('display_images')) {
            foreach ($request->file('display_images') as $index => $file) {
                $newOrder = $maxDisplayOrder + $index + 1;
                $filename = 'display_' . $newOrder . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($basePath, $filename, 'public');

                MsProductImage::create([
                    'product_id' => $product->product_id,
                    'url' => Storage::url($path),
                    'image_type' => 'DISPLAY',
                    'main' => 0,
                    'order' => $newOrder,
                    'created_by' => $createdBy,
                ]);
            }
        }

        // Layout images - APPEND to existing
        if ($request->hasFile('layout_images')) {
            foreach ($request->file('layout_images') as $index => $file) {
                $newOrder = $maxLayoutOrder + $index + 1;
                $filename = 'layout_' . $newOrder . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($basePath, $filename, 'public');

                MsProductImage::create([
                    'product_id' => $product->product_id,
                    'url' => Storage::url($path),
                    'image_type' => 'LAYOUT',
                    'main' => 0,
                    'order' => $newOrder,
                    'created_by' => $createdBy,
                ]);
            }
        }

        // Brochure image - replace existing brochure if exists
        if ($request->hasFile('brochure_image')) {
            // Delete existing brochure first
            $existingBrochure = MsProductImage::where('product_id', $product->product_id)
                ->where('image_type', 'BROCHURE')
                ->first();

            if ($existingBrochure) {
                $this->deleteImageFile($existingBrochure->url);
                $existingBrochure->delete();
            }

            $file = $request->file('brochure_image');
            $filename = 'brochure_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($basePath, $filename, 'public');

            Log::info('Brochure uploaded', [
                'product_id' => $product->product_id,
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::url($path),
            ]);

            MsProductImage::create([
                'product_id' => $product->product_id,
                'url' => Storage::url($path),
                'image_type' => 'BROCHURE',
                'main' => 0,
                'order' => 0,
                'created_by' => $createdBy,
            ]);
        }
    }

    // Delete a single image file from storage
    private function deleteImageFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = str_replace('/storage/', '', $url);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // Delete all images for a product (both storage and database)
    private function deleteProductImages(string $productId): void
    {
        // Delete from database
        MsProductImage::where('product_id', $productId)->delete();

        // Delete folder from storage
        $folderPath = "products/{$productId}";
        if (Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->deleteDirectory($folderPath);
        }
    }

    // Cleanup uploaded files on error
    private function cleanupUploadedFiles(?string $productId): void
    {
        if (!$productId) {
            return;
        }

        $folderPath = "products/{$productId}";
        if (Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->deleteDirectory($folderPath);
        }
    }

    // Check if request has any image files
    private function hasImageFiles(Request $request): bool
    {
        return $request->hasFile('main_image')
            || $request->hasFile('display_images')
            || $request->hasFile('layout_images')
            || $request->hasFile('brochure_image');
    }

    // GET /api/user_product?status={active|draft}&q={keyword}&per_page={limit} - Ambil daftar produk user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 12);

        $query = MsProduct::where('created_by', $user->id)
            ->with(['mainImageRelation', 'brochureImage', 'locations', 'listingTypeDetail', 'productTypeDetail', 'conditionDetail']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($q) {
            $query->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $products = collect($paginator->items())->map(function ($product) {
            // Eager loaded relation
            $mainImage = $product->mainImageRelation;
            $brochureImage = $product->brochureImage;
            $location = $product->locations->first();

            return [
                'product_id' => $product->product_id,
                'title' => $product->title,
                'brochure_title' => $product->brochure_title,
                'selling_price' => $product->selling_price,
                'rental_price' => $product->rental_price,

                'listing_type' => $product->listing_type,
                'listing_type_description' => $product->listingTypeDetail?->description,

                'status' => $product->status,
                'created_at' => $product->created_at,
                'main_image' => $this->publicUrl($mainImage?->url),
                'brochure' => $this->publicUrl($brochureImage?->url),
                'location' => $location ? [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ] : null,

                'product_type' => $product->product_type,
                'product_type_description' => $product->productTypeDetail?->description,

                'condition' => $product->condition,
                'condition_description' => $product->conditionDetail?->description,
            ];
        });

        $payload = [
            'products' => $products,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        return $this->ok($payload, 'Berhasil memuat daftar produk', [
            'filters' => [
                'status' => $status,
                'q' => $q,
                'per_page' => $perPage,
            ],
        ]);
    }

    // POST /api/user_product - Simpan produk baru (draft/publish)
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            $this->getValidationRules(),
            $this->getValidationMessages()
        );

        // Custom brochure validation for VPS compatibility
        $brochureError = $this->validateBrochureImage($request);
        if ($brochureError) {
            return response()->json([
                'success' => false,
                'message' => $brochureError,
            ], 422);
        }

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            // Ambil hanya error pertama
            $firstError = $errors[0] ?? 'Validasi gagal';

            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 422);
        }

        $productId = $this->generateProductId();

        try {
            DB::beginTransaction();

            $user = $request->user();
            $createdBy = $user?->id;

            $product = MsProduct::create([
                'product_id' => $productId,
                'title' => $request->input('title'),
                'brochure_title' => $request->input('brochure_title'),
                'description' => $request->input('description'),
                'condition' => $request->input('condition'),
                'product_type' => $request->input('product_type'),
                'legal' => $request->input('legal'),
                'label' => $request->input('label') ? json_encode($request->input('label')) : null,
                'developer' => $request->input('developer'),
                'specification' => $request->input('specification'),
                'facility' => $request->input('facility'),
                'province' => $request->input('province'),
                'city' => $request->input('city'),
                'area' => $request->input('area'),
                'address' => $request->input('address'),
                'owner_name' => $request->input('owner_name'),
                'owner_phone' => $request->input('owner_phone'),
                'owner_email' => $request->input('owner_email'),
                'owner_nik' => $request->input('owner_nik'),
                'owner_address' => $request->input('owner_address'),
                'user_name' => $request->input('user_name'),
                'user_phone' => $request->input('user_phone'),
                'listing_type' => $request->input('listing_type'),
                'selling_price' => $request->input('selling_price'),
                'rental_price' => $request->input('rental_price'),
                'commission_selling_percentage' => $request->input('commission_selling_percentage'),
                'commission_rent_percentage' => $request->input('commission_rent_percentage'),
                'commission_selling_price' => $this->calculateCommission(
                    $request->input('selling_price'),
                    $request->input('commission_selling_percentage')
                ),
                'commission_rent_price' => $this->calculateCommission(
                    $request->input('rental_price'),
                    $request->input('commission_rent_percentage')
                ),
                'rental_terms' => $request->input('rental_terms'),
                'status' => $request->input('status', 'Draft'),
                'lamudi_sync_status' => 'pending',
                'created_by' => $createdBy,
            ]);

            // Create location if provided
            if ($request->has('location') && is_array($request->input('location'))) {
                $location = $request->input('location');
                MsProductLocation::create([
                    'product_id' => $productId,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'created_by' => $createdBy,
                ]);
            }

            // Handle images
            $this->handleImages($product, $request, $createdBy);

            DB::commit();

            // Sync to Lamudi if published directly
            if ($product->status === 'Publish') {
                $this->syncToLamudi($product, 'auto');
            }

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil disimpan',
                'data' => [
                    'product_id' => $productId,
                    'status' => $product->status,
                    'lamudi_sync_status' => $product->lamudi_sync_status,
                ],
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            // Cleanup uploaded files if any
            $this->cleanupUploadedFiles($productId);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan produk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/user_product/{product_id} - Ambil detail produk user
    public function show(string $productId): JsonResponse
    {
        $product = MsProduct::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $location = $product->locations()->first();
        $mainImage = $product->images()->main()->first();
        $displayImages = $product->displayImages()->get();
        $layoutImages = $product->layoutImages()->get();
        $brochureImage = $product->brochureImage;

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->product_id,
                'title' => $product->title,
                'brochure_title' => $product->brochure_title,
                'description' => $product->description,
                'condition' => $product->condition,
                'product_type' => $product->product_type,
                'legal' => $product->legal,
                'label' => $product->label_array,
                'developer' => $product->developer,
                'specification' => $product->specification_array,
                'facility' => $product->facility_array,
                'province' => $product->province,
                'city' => $product->city,
                'area' => $product->area,
                'address' => $product->address,
                'location' => $location ? [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ] : null,
                'owner_name' => $product->owner_name,
                'owner_phone' => $product->owner_phone,
                'owner_email' => $product->owner_email,
                'owner_nik' => $product->owner_nik,
                'owner_address' => $product->owner_address,
                'user_name' => $product->user_name,
                'user_phone' => $product->user_phone,
                'listing_type' => $product->listing_type,
                'selling_price' => $product->selling_price,
                'rental_price' => $product->rental_price,
                'commission_selling_percentage' => $product->commission_selling_percentage,
                'commission_rent_percentage' => $product->commission_rent_percentage,
                'commission_selling_price' => $product->commission_selling_price,
                'commission_rent_price' => $product->commission_rent_price,
                'rental_terms' => $product->rental_terms,
                'status' => $product->status,
                'images' => [
                    'main' => $this->publicUrl($mainImage?->url),
                    'display' => $displayImages->map(fn($img) => [
                        'id' => $img->id,
                        'url' => $this->publicUrl($img->url),
                        'order' => $img->order,
                    ])->values(),
                    'layout' => $layoutImages->map(fn($img) => [
                        'id' => $img->id,
                        'url' => $this->publicUrl($img->url),
                        'order' => $img->order,
                    ])->values(),
                    'brochure' => $this->publicUrl($brochureImage?->url),
                ],
            ],
        ]);
    }

    // PUT /api/user_product/{product_id} - Update data produk user
    public function update(Request $request, string $productId): JsonResponse
    {
        $product = MsProduct::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        // Debug logging
        Log::info('Update Product Request', [
            'product_id' => $productId,
            'has_main_image' => $request->hasFile('main_image'),
            'has_display_images' => $request->hasFile('display_images'),
            'has_layout_images' => $request->hasFile('layout_images'),
            'has_brochure_image' => $request->hasFile('brochure_image'),
            'all_files' => $request->allFiles(),
        ]);

        $validator = Validator::make(
            $request->all(),
            $this->getValidationRules(true),
            $this->getValidationMessages()
        );

        // Custom brochure validation for VPS compatibility
        $brochureError = $this->validateBrochureImage($request);
        if ($brochureError) {
            return response()->json([
                'success' => false,
                'message' => $brochureError,
            ], 422);
        }

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            // Ambil hanya error pertama
            $firstError = $errors[0] ?? 'Validasi gagal';

            Log::error('Update Product Validation Failed', [
                'product_id' => $productId,
                'errors' => $errors,
            ]);

            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $updatedBy = $user?->id;

            // Track status change for Lamudi sync
            $oldStatus = $product->status;
            $newStatus = $request->input('status', $product->status);

            $product->update([
                'title' => $request->input('title', $product->title),
                'brochure_title' => $request->input('brochure_title', $product->brochure_title),
                'description' => $request->input('description', $product->description),
                'condition' => $request->input('condition', $product->condition),
                'product_type' => $request->input('product_type', $product->product_type),
                'legal' => $request->input('legal', $product->legal),
                'label' => $request->has('label')
                    ? ($request->input('label') ? json_encode($request->input('label')) : null)
                    : $product->label,
                'developer' => $request->input('developer', $product->developer),
                'specification' => $request->input('specification', $product->specification),
                'facility' => $request->input('facility', $product->facility),
                'province' => $request->input('province', $product->province),
                'city' => $request->input('city', $product->city),
                'area' => $request->input('area', $product->area),
                'address' => $request->input('address', $product->address),
                'owner_name' => $request->input('owner_name', $product->owner_name),
                'owner_phone' => $request->input('owner_phone', $product->owner_phone),
                'owner_email' => $request->input('owner_email', $product->owner_email),
                'owner_nik' => $request->input('owner_nik', $product->owner_nik),
                'owner_address' => $request->input('owner_address', $product->owner_address),
                'user_name' => $request->input('user_name', $product->user_name),
                'user_phone' => $request->input('user_phone', $product->user_phone),
                'listing_type' => $request->input('listing_type', $product->listing_type),
                'selling_price' => $request->input('selling_price', $product->selling_price),
                'rental_price' => $request->input('rental_price', $product->rental_price),
                'commission_selling_percentage' => $request->input('commission_selling_percentage', $product->commission_selling_percentage),
                'commission_rent_percentage' => $request->input('commission_rent_percentage', $product->commission_rent_percentage),
                'commission_selling_price' => $this->calculateCommission(
                    $request->input('selling_price', $product->selling_price),
                    $request->input('commission_selling_percentage', $product->commission_selling_percentage)
                ),
                'commission_rent_price' => $this->calculateCommission(
                    $request->input('rental_price', $product->rental_price),
                    $request->input('commission_rent_percentage', $product->commission_rent_percentage)
                ),
                'rental_terms' => $request->input('rental_terms', $product->rental_terms),
                'status' => $newStatus,
                'update_by' => $updatedBy,
            ]);

            // Update location if provided
            if ($request->has('location') && is_array($request->input('location'))) {
                $locationData = $request->input('location');
                $location = $product->locations()->first();

                if ($location) {
                    $location->update([
                        'latitude' => $locationData['latitude'] ?? $location->latitude,
                        'longitude' => $locationData['longitude'] ?? $location->longitude,
                        'update_by' => $updatedBy,
                    ]);
                } else {
                    MsProductLocation::create([
                        'product_id' => $productId,
                        'latitude' => $locationData['latitude'] ?? null,
                        'longitude' => $locationData['longitude'] ?? null,
                        'created_by' => $updatedBy,
                    ]);
                }
            }

            // Handle images - APPEND mode (adds new images, keeps existing)
            if ($this->hasImageFiles($request)) {
                $this->handleImages($product, $request, $updatedBy);
            }

            DB::commit();

            // Sync to Lamudi based on different scenarios
            if ($newStatus === 'Publish') {
                // Scenario 1: Status changed from Draft to Publish -> sync
                if ($oldStatus === 'Draft') {
                    $this->syncToLamudi($product, 'auto');
                }
                // Scenario 2: Already published, and synced -> sync on every update
                elseif ($product->isSyncedWithLamudi()) {
                    $this->syncToLamudi($product, 'update');
                }
                // Scenario 3: Already published, but sync failed -> retry
                elseif ($product->shouldRetryLamudiSync()) {
                    $this->syncToLamudi($product, 'retry');
                }
                // Scenario 4: First time publishing without sync -> sync now
                elseif (empty($product->lamudi_sync_status) || $product->lamudi_sync_status === 'pending') {
                    $this->syncToLamudi($product, 'auto');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => [
                    'product_id' => $product->product_id,
                    'status' => $product->status,
                    'lamudi_sync_status' => $product->lamudi_sync_status,
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /api/user_product/{product_id}/publish - Publish produk draft
    public function publish(Request $request, string $productId): JsonResponse
    {
        $product = MsProduct::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        // Validation before publish
        $errors = [];

        if (empty($product->title)) {
            $errors[] = 'Judul properti (Title) belum diisi';
        }

        if (empty($product->description) || strlen($product->description) < 20) {
            $errors[] = 'Deskripsi properti (Description) minimal 20 karakter';
        }

        if (empty($product->condition)) {
            $errors[] = 'Kondisi properti (Condition) belum dipilih';
        }

        if (empty($product->product_type)) {
            $errors[] = 'Tipe properti (Property Type) belum dipilih';
        }

        if (empty($product->province)) {
            $errors[] = 'Provinsi belum dipilih';
        }

        if (empty($product->address)) {
            $errors[] = 'Alamat lengkap properti (Address) belum diisi';
        }

        if (empty($product->owner_name)) {
            $errors[] = 'Nama pemilik (Owner Name) belum diisi';
        }

        if (empty($product->owner_phone)) {
            $errors[] = 'Nomor telepon pemilik (Owner Phone) belum diisi';
        }

        if (empty($product->user_name)) {
            $errors[] = 'Nama kontak (User Name) belum diisi';
        }

        if (empty($product->user_phone)) {
            $errors[] = 'Nomor telepon kontak (User Phone) belum diisi';
        }

        if (empty($product->listing_type)) {
            $errors[] = 'Tipe listing (Listing Type) belum dipilih. Pilih "Jual" atau "Sewa"';
        }

        // Validasi coordinates (WAJIB untuk integrasi Lamudi)
        $location = $product->locations->first();
        if (!$location || empty($location->latitude) || empty($location->longitude)) {
            $errors[] = 'Lokasi properti di peta (Latitude & Longitude) belum lengkap. Silakan pin lokasi di peta saat edit produk.';
        }

        // Validasi owner email (WAJIB untuk integrasi Lamudi)
        if (empty($product->owner_email)) {
            $errors[] = 'Email pemilik properti (Owner Email) belum diisi. Wajib untuk notifikasi';
        }

        // Check if at least 1 display image exists
        $displayImageCount = $product->displayImages()->count();
        if ($displayImageCount < 1) {
            $errors[] = 'Belum ada foto properti. Minimal upload 1 foto tampil (Display Image)';
        }

        if (!empty($errors)) {
            // Ambil hanya error pertama
            $firstError = $errors[0] ?? 'Validasi gagal';

            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 422);
        }

        try {
            $user = $request->user();

            $product->update([
                'status' => 'Publish',
                'lamudi_sync_status' => $product->lamudi_sync_status ?? 'pending',
                'update_by' => $user?->id,
            ]);

            // Sync to Lamudi
            $this->syncToLamudi($product, 'manual');

            // Note: Not refreshing product as sync is now async
            // The status will be updated by the job in the background

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diterbitkan',
                'data' => [
                    'product_id' => $product->product_id,
                    'status' => 'Publish',
                    'lamudi_sync_status' => $product->lamudi_sync_status,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerbitkan produk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync product to Lamudi via Queue
     * Dispatches job to background queue for async processing
     */
    private function syncToLamudi(MsProduct $product, string $action = 'auto'): void
    {
        // Dispatch job to queue for async processing
        SyncToLamudiJob::dispatch($product->product_id, $action);

        Log::info('Lamudi sync job dispatched', [
            'product_id' => $product->product_id,
            'action' => $action,
        ]);
    }

    // DELETE /api/user_product/images/{image_id} - Hapus gambar produk specific
    public function deleteImage(int $imageId): JsonResponse
    {
        $image = MsProductImage::find($imageId);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Foto tidak ditemukan',
            ], 404);
        }

        try {
            // Delete file from storage
            $this->deleteImageFile($image->url);

            // Delete from database
            $image->delete();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Convert storage path to public URL
    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        return asset($path);
    }

    /**
     * GET /api/user_product/{product_id}/lamudi/preview
     * Preview data yang akan dikirim ke Proppit/Lamudi
     * Berguna untuk debugging dan validasi sebelum sync
     */
    public function lamudiPreview(string $productId): JsonResponse
    {
        $product = MsProduct::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        try {
            $user = $product->creator;
            $apiConfig = $user?->msApi;

            // In preview, we might want to show error if config is missing
            if (!$user || $user->lamudi_api !== 'ON' || !$apiConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfigurasi Lamudi/Proppit belum diaktifkan atau belum lengkap untuk user ini.',
                    'debug' => [
                        'lamudi_api' => $user->lamudi_api ?? 'null',
                        'has_ms_api' => (bool) $apiConfig,
                    ]
                ], 400);
            }

            $mapper = new LamudiAdMapper($apiConfig->api_pubid);

            // Prepare images for Lamudi
            $displayImages = $product->displayImages()->get()->map(function ($img) {
                return [
                    'url' => $this->publicUrl($img->url),
                    'type' => 'DISPLAY',
                ];
            })->toArray();

            $layoutImages = $product->layoutImages()->get()->map(function ($img) {
                return [
                    'url' => $this->publicUrl($img->url),
                    'type' => 'LAYOUT',
                ];
            })->toArray();

            $allImages = array_merge($displayImages, $layoutImages);

            // Get preview data
            $preview = $mapper->previewLamudiAd($product, $allImages);

            if (!$preview['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mempersiapkan data Lamudi',
                    'error' => $preview['error'] ?? 'Unknown error',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Preview data Lamudi berhasil diambil',
                'data' => $preview['data'],
                'summary' => $preview['summary'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mempersiapkan preview data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/user_product/{product_id}
     * Delete product and remove from Proppit if synced
     */
    public function destroy(string $productId): JsonResponse
    {
        $product = MsProduct::where('product_id', $productId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete from Proppit if synced
            if ($product->isSyncedWithLamudi() && !empty($product->lamudi_reference_id)) {
                try {
                    $user = $product->creator;
                    $apiConfig = $user?->msApi;

                    if ($user && $user->lamudi_api === 'ON' && $apiConfig) {
                        $lamudiService = new \App\Services\Lamudi\LamudiService($apiConfig);
                        $lamudiService->deleteAd($product->lamudi_reference_id);
                        Log::info('Lamudi ad deleted', [
                            'product_id' => $product->product_id,
                            'reference_id' => $product->lamudi_reference_id,
                        ]);
                    } else {
                        Log::info('Skipping Lamudi ad deletion: User API disabled or config missing', [
                            'product_id' => $product->product_id,
                            'lamudi_api' => $user->lamudi_api ?? 'null',
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log warning but don't prevent deletion
                    Log::warning('Failed to delete from Lamudi, continuing with local deletion', [
                        'product_id' => $product->product_id,
                        'reference_id' => $product->lamudi_reference_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete product images from storage
            $this->deleteProductImages($productId);

            // Delete product
            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus' . ($product->isSyncedWithLamudi() ? ' (termasuk dari Proppit)' : ''),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
