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

    public function __construct(\App\Models\UserProduct\MsApi $apiConfig)
    {
        $this->baseUrl = config('services.lamudi.base_url', 'https://real-time.proppit.com/api/v2');
        $this->country = config('services.lamudi.country', 'ID');

        $this->user = $apiConfig->api_user;
        $this->password = $apiConfig->api_password;
        $this->publisherId = $apiConfig->api_pubid;
    }

    /**
     * Get dynamic cache key based on current credentials
     */
    private function getCacheKey(string $type): string
    {
        $hash = md5($this->user . $this->publisherId);
        return "lamudi_{$type}_{$hash}";
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
        $tokenKey = $this->getCacheKey('token');
        $expKey = $this->getCacheKey('expiration');

        $cachedToken = Cache::get($tokenKey);
        $cachedExpiration = Cache::get($expKey);

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
        Cache::put($tokenKey, $token, $ttl);
        Cache::put($expKey, $expiration, $ttl);

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
                $parsedError = $this->parseErrorResponse($response);
                Log::error('Failed to create Lamudi ad', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'parsed_error' => $parsedError,
                ]);
                throw new \Exception('Failed to create ad on Lamudi: ' . $parsedError);
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
                $parsedError = $this->parseErrorResponse($response);
                Log::error('Failed to update Lamudi ad', [
                    'reference_id' => $referenceId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'parsed_error' => $parsedError,
                ]);
                throw new \Exception('Failed to update ad on Lamudi: ' . $parsedError);
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

    /**
     * Parse error response from Proppit API
     * API returns: {"status":400,"requestId":"xxx","error":"{\"error\":{\"message\":\"...\"}}"}
     */
    private function parseErrorResponse($response): string
    {
        $body = $response->body();
        $data = json_decode($body, true);

        if (!$data) {
            return $body;
        }

        // Try to extract error message from nested structure
        if (isset($data['error'])) {
            $errorData = json_decode($data['error'], true);
            if ($errorData && isset($errorData['error']['message'])) {
                return $errorData['error']['message'];
            }
            // Try direct error field
            if (is_string($data['error'])) {
                return $data['error'];
            }
        }

        return $body;
    }

    /**
     * Check if an error is temporary and should be retried
     * Temporary errors: 5xx status codes, timeout, connection issues
     */
    private function isTemporaryError(\Exception $e, int $statusCode = 0): bool
    {
        $message = strtolower($e->getMessage());

        // Retry on 5xx server errors
        if ($statusCode >= 500 && $statusCode < 600) {
            return true;
        }

        // Retry on timeout
        if (str_contains($message, 'timeout')) {
            return true;
        }

        // Retry on connection errors
        if (str_contains($message, 'connection')) {
            return true;
        }

        // Retry on specific network errors
        if (str_contains($message, 'could not resolve') ||
            str_contains($message, 'network') ||
            str_contains($message, 'dns')) {
            return true;
        }

        // Retry on 502/503/504 specifically
        if (str_contains($message, '502') ||
            str_contains($message, '503') ||
            str_contains($message, '504') ||
            str_contains($message, 'bad gateway') ||
            str_contains($message, 'service unavailable') ||
            str_contains($message, 'gateway timeout')) {
            return true;
        }

        return false;
    }

    /**
     * Create ad on Lamudi with automatic retry for temporary errors
     *
     * @param array $adData
     * @param int $maxRetries Maximum number of retry attempts
     * @return array
     * @throws \Exception
     */
    public function createAdWithRetry(array $adData, int $maxRetries = 3): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->createAd($adData);
            } catch (\Exception $e) {
                $lastException = $e;

                // Get status code if available
                $statusCode = 0;
                if (method_exists($e, 'getResponse')) {
                    $response = $e->getResponse();
                    if ($response) {
                        $statusCode = $response->getStatusCode();
                    }
                }

                // Don't retry if it's not a temporary error
                if (!$this->isTemporaryError($e, $statusCode)) {
                    Log::warning('Non-retryable error when creating Lamudi ad', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Don't retry on last attempt
                if ($attempt >= $maxRetries) {
                    break;
                }

                // Calculate delay with exponential backoff (5s, 10s, 20s, max 60s)
                $delay = min(5 * (2 ** ($attempt - 1)), 60);

                Log::warning('Retrying Lamudi createAd request due to temporary error', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay_seconds' => $delay,
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode,
                ]);

                sleep($delay);
            }
        }

        // All retries exhausted, throw the last exception
        throw $lastException;
    }

    /**
     * Update ad on Lamudi with automatic retry for temporary errors
     *
     * @param string $referenceId
     * @param array $adData
     * @param int $maxRetries Maximum number of retry attempts
     * @return array
     * @throws \Exception
     */
    public function updateAdWithRetry(string $referenceId, array $adData, int $maxRetries = 3): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->updateAd($referenceId, $adData);
            } catch (\Exception $e) {
                $lastException = $e;

                // Get status code if available
                $statusCode = 0;
                if (method_exists($e, 'getResponse')) {
                    $response = $e->getResponse();
                    if ($response) {
                        $statusCode = $response->getStatusCode();
                    }
                }

                // Don't retry if it's not a temporary error
                if (!$this->isTemporaryError($e, $statusCode)) {
                    Log::warning('Non-retryable error when updating Lamudi ad', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'reference_id' => $referenceId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Don't retry on last attempt
                if ($attempt >= $maxRetries) {
                    break;
                }

                // Calculate delay with exponential backoff
                $delay = min(5 * (2 ** ($attempt - 1)), 60);

                Log::warning('Retrying Lamudi updateAd request due to temporary error', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay_seconds' => $delay,
                    'reference_id' => $referenceId,
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode,
                ]);

                sleep($delay);
            }
        }

        // All retries exhausted, throw the last exception
        throw $lastException;
    }
}
