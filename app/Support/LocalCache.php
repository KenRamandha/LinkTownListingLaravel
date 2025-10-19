<?php

namespace App\Support;

class LocalCache
{
    protected static function basePath(): string
    {
        $path = storage_path('framework/cache/custom');
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        return $path;
    }

    protected static function pathForKey(string $key): string
    {
        $hash = sha1($key);
        $dir = self::basePath() . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    public static function remember(string $key, int $seconds, callable $callback)
    {
        $payload = self::getPayload($key);
        if (!is_null($payload) && (is_null($payload['expires_at']) || $payload['expires_at'] > microtime(true))) {
            return $payload['value'];
        }

        $value = $callback();
        self::storePayload($key, $value, $seconds > 0 ? microtime(true) + $seconds : null);
        return $value;
    }

    public static function rememberForever(string $key, callable $callback)
    {
        return self::remember($key, 0, $callback);
    }

    public static function put(string $key, $value, int $seconds = 0): void
    {
        self::storePayload($key, $value, $seconds > 0 ? microtime(true) + $seconds : null);
    }

    public static function get(string $key, $default = null)
    {
        $payload = self::getPayload($key);
        if (is_null($payload)) {
            return $default;
        }

        if (!is_null($payload['expires_at']) && $payload['expires_at'] <= microtime(true)) {
            self::forget($key);
            return $default;
        }

        return $payload['value'];
    }

    public static function forget(string $key): void
    {
        $path = self::pathForKey($key);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Atomically add a value only when the key is missing or expired.
     */
    public static function add(string $key, $value, int $seconds): bool
    {
        $payload = self::getPayload($key);
        if (!is_null($payload) && (is_null($payload['expires_at']) || $payload['expires_at'] > microtime(true))) {
            return false;
        }

        self::storePayload($key, $value, microtime(true) + $seconds);
        return true;
    }

    protected static function getPayload(string $key): ?array
    {
        $path = self::pathForKey($key);
        if (!file_exists($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }

        return $payload;
    }

    protected static function storePayload(string $key, $value, ?float $expiresAt): void
    {
        $path = self::pathForKey($key);
        $payload = serialize([
            'expires_at' => $expiresAt,
            'value' => $value,
        ]);
        @file_put_contents($path, $payload, LOCK_EX);
    }
}
