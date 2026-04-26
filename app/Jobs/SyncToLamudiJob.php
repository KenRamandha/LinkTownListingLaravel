<?php

namespace App\Jobs;

use App\Models\UserProduct\MsProduct;
use App\Services\Lamudi\LamudiAdMapper;
use App\Services\Lamudi\LamudiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncToLamudiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Retry delay in seconds (exponential backoff will be applied)
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $productId,
        private string $action = 'auto' // 'auto', 'manual', 'retry'
    ) {
        // Set queue name for Lamudi sync jobs
        $this->onQueue('lamudi-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $product = MsProduct::find($this->productId);

        if (!$product) {
            Log::warning('Product not found for Lamudi sync', [
                'product_id' => $this->productId,
            ]);
            return;
        }

        // Only sync published products
        if ($product->status !== 'Publish') {
            Log::info('Skipping Lamudi sync for non-published product', [
                'product_id' => $this->productId,
                'status' => $product->status,
            ]);
            return;
        }

        // Get the user who created the product to check their Lamudi settings
        $user = $product->creator;

        if (!$user) {
            Log::warning('Creator not found for product, skipping Lamudi sync', [
                'product_id' => $product->product_id,
            ]);
            return;
        }

        // 1. Check if user enabled Lamudi API
        if ($user->lamudi_api !== 'ON') {
            Log::info('Skipping Lamudi sync: User Lamudi API is OFF', [
                'product_id' => $product->product_id,
                'user_id' => $user->id,
            ]);
            return;
        }

        // 2. Check if user has API config linked
        if (!$user->ms_api) {
            Log::info('Skipping Lamudi sync: User has no API configuration linked (ms_api is null)', [
                'product_id' => $product->product_id,
                'user_id' => $user->id,
            ]);
            return;
        }

        // 3. Fetch API config from ms_api table
        $apiConfig = $user->msApi;
        if (!$apiConfig) {
            Log::warning('Skipping Lamudi sync: API config ID ' . $user->ms_api . ' not found in ms_api table', [
                'product_id' => $product->product_id,
                'user_id' => $user->id,
            ]);
            return;
        }

        $lamudiService = new LamudiService($apiConfig);
        $mapper = new LamudiAdMapper($apiConfig->api_pubid);

        try {
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

            // Map product data to Lamudi format (includes validation)
            $lamudiAdData = $mapper->mapToLamudiAd($product, $allImages);

            // Log the data being sent to Lamudi for debugging
            Log::info('Sending data to Lamudi (Queue)', [
                'product_id' => $product->product_id,
                'action' => $this->action,
                'has_coordinates' => !empty($lamudiAdData['property']['location']['coordinates']['lat']),
                'has_geo' => !empty($lamudiAdData['property']['location']['geo']),
                'has_address' => !empty($lamudiAdData['property']['location']['address']),
                'pictures_count' => count($lamudiAdData['multimedia']['pictures'] ?? []),
                'floorplans_count' => count($lamudiAdData['multimedia']['floorPlans'] ?? []),
                'total_images' => count($allImages),
                'display_images' => count($displayImages),
                'layout_images' => count($layoutImages),
                'picture_urls' => array_column($lamudiAdData['multimedia']['pictures'] ?? [], 'url'),
                'floorplan_urls' => array_column($lamudiAdData['multimedia']['floorPlans'] ?? [], 'url'),
            ]);

            // Check if already synced, then update, otherwise create
            if ($product->isSyncedWithLamudi()) {
                // Update existing ad with retry mechanism
                $lamudiService->updateAdWithRetry($product->lamudi_reference_id, $lamudiAdData);
                Log::info('Lamudi ad updated (Queue)', [
                    'product_id' => $product->product_id,
                    'lamudi_reference_id' => $product->lamudi_reference_id,
                ]);
            } else {
                // Create new ad with retry mechanism
                $response = $lamudiService->createAdWithRetry($lamudiAdData);
                $product->markAsLamudiSynced($response['referenceId'] ?? $product->product_id);
                Log::info('Lamudi ad created (Queue)', [
                    'product_id' => $product->product_id,
                    'lamudi_reference_id' => $product->lamudi_reference_id,
                ]);
            }
        } catch (\Exception $e) {
            // Parse error message for better user feedback
            $userFriendlyError = $this->parseLamudiError($e->getMessage());

            // Mark as failed but don't prevent the publish operation
            $product->markAsLamudiFailed($userFriendlyError);
            Log::error('Failed to sync product to Lamudi (Queue)', [
                'product_id' => $product->product_id,
                'raw_error' => $e->getMessage(),
                'user_friendly_error' => $userFriendlyError,
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry if attempts remaining
            throw $e;
        }
    }

    /**
     * Get public URL from storage path
     */
    private function publicUrl(string $path): string
    {
        // If already a full URL, return as-is
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Remove leading slash and 'storage/' if present to avoid double path
        $cleanPath = ltrim($path, '/');
        $cleanPath = preg_replace('#^storage/#', '', $cleanPath);

        return config('app.url') . '/storage/' . $cleanPath;
    }

    /**
     * Parse Lamudi API error to user-friendly message
     */
    private function parseLamudiError(string $errorMessage): string
    {
        // Check for geolocation errors
        if (str_contains($errorMessage, 'Could not geolocate')) {
            return 'Lokasi tidak dapat ditemukan. Pastikan alamat lengkap dan pin lokasi di peta sudah benar.';
        }

        // Check for validation errors
        if (str_contains($errorMessage, 'Validation failed')) {
            return $errorMessage; // Already user-friendly from mapper
        }

        // Check for coordinates errors
        if (str_contains($errorMessage, 'Latitude') || str_contains($errorMessage, 'Longitude')) {
            return $errorMessage; // Already user-friendly from mapper
        }

        // Check for authentication errors
        if (str_contains($errorMessage, 'authenticate') || str_contains($errorMessage, 'token')) {
            return 'Gagal autentikasi ke Proppit. Silakan coba lagi atau hubungi admin.';
        }

        // Default generic message
        return 'Gagal sync ke Proppit: ' . $errorMessage;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $product = MsProduct::find($this->productId);

        if ($product) {
            Log::error('Lamudi sync job failed permanently', [
                'product_id' => $product->product_id,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);
        }
    }
}
