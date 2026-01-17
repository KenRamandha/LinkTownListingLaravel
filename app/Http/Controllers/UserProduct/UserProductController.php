<?php

namespace App\Http\Controllers\UserProduct;

use App\Http\Controllers\Controller;
use App\Models\UserProduct\MsProduct;
use App\Models\UserProduct\MsProductImage;
use App\Models\UserProduct\MsProductLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ];
    }

    // Handle image uploads for a product (APPEND mode - keeps existing images)
    private function handleImages(MsProduct $product, Request $request, ?string $createdBy = null): void
    {
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
            || $request->hasFile('layout_images');
    }

    // GET /api/user_product?status={active|draft}&q={keyword}&per_page={limit} - Ambil daftar produk user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 12);

        $query = MsProduct::where('created_by', $user->id) 
            ->with(['mainImageRelation', 'locations', 'listingTypeDetail', 'productTypeDetail', 'conditionDetail']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($q) {
            $query->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $products = collect($paginator->items())->map(function ($product) {
            // Eager loaded relation
            $mainImage = $product->mainImageRelation;
            $location = $product->locations->first();
            
            return [
                'product_id' => $product->product_id,
                'title' => $product->title,
                'selling_price' => $product->selling_price,
                'rental_price' => $product->rental_price,
                
                'listing_type' => $product->listing_type,
                'listing_type_description' => $product->listingTypeDetail?->description,
                
                'status' => $product->status,
                'created_at' => $product->created_at,
                'main_image' => $this->publicUrl($mainImage?->url),
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
        $validator = Validator::make($request->all(), $this->getValidationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
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

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil disimpan',
                'data' => [
                    'product_id' => $productId,
                    'status' => $product->status,
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

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->product_id,
                'title' => $product->title,
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

        $validator = Validator::make($request->all(), $this->getValidationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $updatedBy = $user?->id;

            $product->update([
                'title' => $request->input('title', $product->title),
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
                'status' => $request->input('status', $product->status),
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

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => [
                    'product_id' => $product->product_id,
                    'status' => $product->status,
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
            $errors[] = 'Title tidak boleh kosong';
        }

        if (empty($product->description) || str_word_count($product->description) < 20) {
            $errors[] = 'Description minimal 20 kata';
        }

        if (empty($product->condition)) {
            $errors[] = 'Condition tidak boleh kosong';
        }

        if (empty($product->product_type)) {
            $errors[] = 'Product Type tidak boleh kosong';
        }

        if (empty($product->province)) {
            $errors[] = 'Province tidak boleh kosong';
        }

        if (empty($product->address)) {
            $errors[] = 'Address tidak boleh kosong';
        }

        if (empty($product->owner_name)) {
            $errors[] = 'Owner Name tidak boleh kosong';
        }

        if (empty($product->owner_phone)) {
            $errors[] = 'Owner Phone tidak boleh kosong';
        }

        if (empty($product->user_name)) {
            $errors[] = 'User Name tidak boleh kosong';
        }

        if (empty($product->user_phone)) {
            $errors[] = 'User Phone tidak boleh kosong';
        }

        if (empty($product->listing_type)) {
            $errors[] = 'Listing Type tidak boleh kosong';
        }

        // Check if at least 1 display image exists
        $displayImageCount = $product->displayImages()->count();
        if ($displayImageCount < 1) {
            $errors[] = 'Minimal 1 foto display diperlukan';
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal untuk publish',
                'errors' => $errors,
            ], 422);
        }

        try {
            $user = $request->user();
            
            $product->update([
                'status' => 'Publish',
                'update_by' => $user?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diterbitkan',
                'data' => [
                    'product_id' => $product->product_id,
                    'status' => 'Publish',
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
}
