<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ProductCache
{
    protected static function homeIndexKey(): string
    {
        return 'products:home:index';
    }

    protected static function homeKey(?string $propertyStatus, int $limit): string
    {
        $statusPart = is_null($propertyStatus) || $propertyStatus === ''
            ? 'null'
            : sha1($propertyStatus);

        return "products:home:{$statusPart}:{$limit}";
    }

    protected static function filtersKey(): string
    {
        return 'products:filters';
    }

    protected static function detailKey(string $productId): string
    {
        return "products:detail:{$productId}";
    }

    protected static function registerHomeKey(string $cacheKey): void
    {
        $indexKey = self::homeIndexKey();
        $keys = LocalCache::get($indexKey, []);

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            LocalCache::put($indexKey, $keys, 86400);
        }
    }

    protected static function removeHomeKey(string $cacheKey): void
    {
        $indexKey = self::homeIndexKey();
        $keys = LocalCache::get($indexKey, []);

        if (!$keys) {
            return;
        }

        $filtered = array_values(array_filter($keys, fn ($key) => $key !== $cacheKey));
        if ($filtered) {
            LocalCache::put($indexKey, $filtered, 86400);
        } else {
            LocalCache::forget($indexKey);
        }
    }

    public static function rememberHome(?string $propertyStatus, int $limit, callable $callback, int $seconds = 120): array
    {
        $cacheKey = self::homeKey($propertyStatus, $limit);

        $data = LocalCache::remember($cacheKey, $seconds, function () use ($callback, $cacheKey) {
            $value = self::toArray($callback());
            self::registerHomeKey($cacheKey);
            return $value;
        });

        return self::toArray($data);
    }

    public static function forgetHome(?string $propertyStatus = null, ?int $limit = null): void
    {
        if (!is_null($propertyStatus) && !is_null($limit)) {
            $key = self::homeKey($propertyStatus, $limit);
            LocalCache::forget($key);
            self::removeHomeKey($key);
            return;
        }

        $indexKey = self::homeIndexKey();
        $keys = LocalCache::get($indexKey, []);
        foreach ($keys as $key) {
            LocalCache::forget($key);
        }
        LocalCache::forget($indexKey);
    }

    public static function rememberFilters(callable $callback, int $seconds = 600): array
    {
        $data = LocalCache::remember(self::filtersKey(), $seconds, function () use ($callback) {
            return self::toArray($callback());
        });

        return self::toArray($data);
    }

    public static function forgetFilters(): void
    {
        LocalCache::forget(self::filtersKey());
    }

    public static function rememberDetail(string $productId, callable $callback, int $seconds = 300): array
    {
        $data = LocalCache::remember(self::detailKey($productId), $seconds, function () use ($callback) {
            return self::toArray($callback());
        });

        return self::toArray($data);
    }

    public static function forgetDetail(string $productId): void
    {
        LocalCache::forget(self::detailKey($productId));
    }

    public static function forgetForProduct(string $productId): void
    {
        self::forgetDetail($productId);
        self::forgetHome();
        self::forgetFilters();
    }

    protected static function toArray($value)
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($item) => self::toArray($item))->all();
        }

        if ($value instanceof \Traversable) {
            $array = [];
            foreach ($value as $key => $item) {
                $array[$key] = self::toArray($item);
            }
            return $array;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::toArray($item);
            }
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return self::toArray($value->toArray());
        }

        return $value;
    }
}
