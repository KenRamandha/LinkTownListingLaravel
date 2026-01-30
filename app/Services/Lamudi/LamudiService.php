<?php

namespace App\Services\Lamudi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LamudiService
{
    private string $baseUrl;
    private string $user;
    private string $password;
    private string $publisherId;
    private string $country;
    private const CACHE_KEY_TOKEN = 'lamudi_api_token';
    private const CACHE_KEY_EXPIRATION = 'lamudi_token_expiration';

    public function __construct()
    {
        $this->baseUrl = config('services.lamudi.base_url', 'https://real-time.proppit.com/api/v2');
        $this->user = config('services.lamudi.user');
        $this->password = config('services.lamudi.password');
        $this->publisherId = config('services.lamudi.publisher_id');
        $this->country = config('services.lamudi.country', 'ID');
    }

    /**
     * Get authenticated HTTP client with bearer token
     */
    private function httpClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->getOrCreateToken())
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Get existing token or create new one if expired
     * Token is cached in Laravel Cache to avoid unnecessary API calls
     */
    private function getOrCreateToken(): string
    {
        $cachedToken = Cache::get(self::CACHE_KEY_TOKEN);
        $cachedExpiration = Cache::get(self::CACHE_KEY_EXPIRATION);

        // Check if token exists in cache and not expired
        if ($cachedToken && $cachedExpiration && $cachedExpiration > time()) {
            return $cachedToken;
        }

        // Request new token (cache miss or expired)
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30)
            ->post('/token', [
                'user' => $this->user,
                'password' => $this->password,
            ]);

        if (!$response->successful()) {
            Log::error('Failed to get Lamudi token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to authenticate with Lamudi API');
        }

        $data = $response->json();
        $token = $data['token'];
        $expiration = $data['expiration'];

        // Store token in cache (expires 5 minutes before actual expiration for safety)
        $ttl = max(0, $expiration - time() - 300);
        Cache::put(self::CACHE_KEY_TOKEN, $token, $ttl);
        Cache::put(self::CACHE_KEY_EXPIRATION, $expiration, $ttl);

        Log::info('Lamudi token refreshed', [
            'expires_at' => date('Y-m-d H:i:s', $expiration),
            'ttl' => $ttl . ' seconds',
        ]);

        return $token;
    }

    /**
     * Create ad on Lamudi
     *
     * @param array $adData
     * @return array
     * @throws \Exception
     */
    public function createAd(array $adData): array
    {
        try {
            $response = $this->httpClient()->post("/proppit/{$this->country}/ads", $adData);

            if (!$response->successful()) {
                Log::error('Failed to create Lamudi ad', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to create ad on Lamudi: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception when creating Lamudi ad', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Update ad on Lamudi
     *
     * @param string $referenceId
     * @param array $adData
     * @return array
     * @throws \Exception
     */
    public function updateAd(string $referenceId, array $adData): array
    {
        try {
            $response = $this->httpClient()->put("/proppit/{$this->country}/ads/{$referenceId}", $adData);

            if (!$response->successful()) {
                Log::error('Failed to update Lamudi ad', [
                    'reference_id' => $referenceId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to update ad on Lamudi: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception when updating Lamudi ad', [
                'reference_id' => $referenceId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete ad from Lamudi
     *
     * @param string $referenceId
     * @return bool
     * @throws \Exception
     */
    public function deleteAd(string $referenceId): bool
    {
        try {
            $response = $this->httpClient()->delete("/proppit/{$this->country}/ads/{$referenceId}", [
                'externalId' => $this->publisherId,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to delete Lamudi ad', [
                    'reference_id' => $referenceId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception when deleting Lamudi ad', [
                'reference_id' => $referenceId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get ad from Lamudi
     *
     * @param string $referenceId
     * @return array|null
     */
    public function getAd(string $referenceId): ?array
    {
        try {
            $response = $this->httpClient()->get("/proppit/{$this->country}/ads/{$referenceId}", [
                'externalId' => $this->publisherId,
            ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception when getting Lamudi ad', [
                'reference_id' => $referenceId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get property types for Indonesia
     *
     * @return array|null
     */
    public function getPropertyTypes(): ?array
    {
        try {
            $response = $this->httpClient()->get("/proppit/{$this->country}/property-types");

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception when getting Lamudi property types', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
