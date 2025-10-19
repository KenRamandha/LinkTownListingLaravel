<?php

namespace App\Support;

use Illuminate\Support\Collection;

class AttendanceCache
{
    public static function geofencesKey(string $companyId): string
    {
        return "attendance:geofences:{$companyId}";
    }

    public static function rememberGeofences(string $companyId, callable $callback, int $seconds = 300): array
    {
        return LocalCache::remember(self::geofencesKey($companyId), $seconds, function () use ($callback) {
            $result = $callback();

            if (is_array($result)) {
                return $result;
            }

            if ($result instanceof \Traversable) {
                return iterator_to_array($result);
            }

            return (array) $result;
        });
    }

    public static function forgetGeofences(string $companyId): void
    {
        LocalCache::forget(self::geofencesKey($companyId));
        self::forgetOverviewForCompany($companyId);
    }

    public static function userLogsKey(string $userId, string $date): string
    {
        return "attendance:logs:{$userId}:{$date}";
    }

    public static function rememberUserLogs(string $userId, string $date, callable $callback, int $seconds = 120): Collection
    {
        $cached = LocalCache::remember(self::userLogsKey($userId, $date), $seconds, function () use ($callback) {
            $result = $callback();

            if ($result instanceof Collection) {
                return $result->map(fn ($row) => (array) $row)->all();
            }

            if ($result instanceof \Traversable) {
                return iterator_to_array($result);
            }

            return $result;
        });

        if ($cached instanceof Collection) {
            return $cached;
        }

        if (is_array($cached)) {
            return collect($cached)->map(fn ($row) => (object) $row);
        }

        return collect();
    }

    public static function forgetUserLogs(string $userId, string $date): void
    {
        LocalCache::forget(self::userLogsKey($userId, $date));
    }

    public static function shiftKey(string $companyId, string $shiftId): string
    {
        return "attendance:shift:{$companyId}:{$shiftId}";
    }

    public static function rememberShift(string $companyId, string $shiftId, callable $callback, int $seconds = 300): ?object
    {
        $cached = LocalCache::remember(self::shiftKey($companyId, $shiftId), $seconds, function () use ($callback) {
            $result = $callback();

            if (is_null($result)) {
                return null;
            }

            if (is_object($result)) {
                return (array) $result;
            }

            return $result;
        });

        if (is_null($cached)) {
            return null;
        }

        if (is_array($cached)) {
            return (object) $cached;
        }

        return $cached;
    }

    public static function forgetShift(string $companyId, string $shiftId): void
    {
        LocalCache::forget(self::shiftKey($companyId, $shiftId));
        self::forgetOverviewForCompany($companyId);
    }

    protected static function overviewKey(string $companyId, string $userId, string $date, ?float $lat, ?float $lng): string
    {
        $latPart = is_null($lat) ? 'null' : number_format($lat, 6, '.', '');
        $lngPart = is_null($lng) ? 'null' : number_format($lng, 6, '.', '');
        return "attendance:overview:{$companyId}:{$userId}:{$date}:{$latPart}:{$lngPart}";
    }

    protected static function overviewIndexKey(string $companyId, string $userId, string $date): string
    {
        return "attendance:overview:index:{$companyId}:{$userId}:{$date}";
    }

    protected static function overviewCompanyIndexKey(string $companyId): string
    {
        return "attendance:overview:index:company:{$companyId}";
    }

    protected static function overviewMetaKey(string $cacheKey): string
    {
        return "attendance:overview:meta:{$cacheKey}";
    }

    public static function rememberOverview(
        string $companyId,
        string $userId,
        string $date,
        ?float $lat,
        ?float $lng,
        callable $callback,
        int $seconds = 60
    ): array {
        $cacheKey = self::overviewKey($companyId, $userId, $date, $lat, $lng);

        return LocalCache::remember($cacheKey, $seconds, function () use ($callback, $cacheKey, $companyId, $userId, $date) {
            $value = $callback();
            self::registerOverviewKey($companyId, $userId, $date, $cacheKey);
            LocalCache::put(self::overviewMetaKey($cacheKey), [
                'company_id' => $companyId,
                'user_id' => $userId,
                'date' => $date,
            ], 3600);
            return $value;
        });
    }

    public static function forgetOverview(string $companyId, string $userId, string $date): void
    {
        $indexKey = self::overviewIndexKey($companyId, $userId, $date);
        $keys = LocalCache::get($indexKey, []);
        foreach ($keys as $key) {
            LocalCache::forget($key);
        }
        LocalCache::forget($indexKey);

        $companyIndex = self::overviewCompanyIndexKey($companyId);
        $companyKeys = LocalCache::get($companyIndex, []);
        if (!empty($companyKeys)) {
            $filtered = array_values(array_diff($companyKeys, $keys));
            if ($filtered) {
                LocalCache::put($companyIndex, $filtered, 3600);
            } else {
                LocalCache::forget($companyIndex);
            }
        }

        foreach ($keys as $key) {
            self::forgetOverviewMeta($key);
        }
    }

    public static function forgetOverviewForCompany(string $companyId): void
    {
        $companyIndex = self::overviewCompanyIndexKey($companyId);
        $keys = LocalCache::get($companyIndex, []);
        foreach ($keys as $key) {
            LocalCache::forget($key);
            self::forgetOverviewMeta($key);
        }
        LocalCache::forget($companyIndex);
    }

    protected static function registerOverviewKey(string $companyId, string $userId, string $date, string $cacheKey): void
    {
        $indexKey = self::overviewIndexKey($companyId, $userId, $date);
        $keys = LocalCache::get($indexKey, []);
        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            LocalCache::put($indexKey, $keys, 3600);
        }

        $companyIndex = self::overviewCompanyIndexKey($companyId);
        $companyKeys = LocalCache::get($companyIndex, []);
        if (!in_array($cacheKey, $companyKeys, true)) {
            $companyKeys[] = $cacheKey;
            LocalCache::put($companyIndex, $companyKeys, 3600);
        }
    }

    protected static function forgetOverviewMeta(string $cacheKey): void
    {
        $metaKey = self::overviewMetaKey($cacheKey);
        $meta = LocalCache::get($metaKey, []);
        if (isset($meta['company_id'], $meta['user_id'], $meta['date'])) {
            $indexKey = self::overviewIndexKey($meta['company_id'], $meta['user_id'], $meta['date']);
            $keys = LocalCache::get($indexKey, []);
            if ($keys) {
                $filtered = array_values(array_filter($keys, fn ($k) => $k !== $cacheKey));
                if ($filtered) {
                    LocalCache::put($indexKey, $filtered, 3600);
                } else {
                    LocalCache::forget($indexKey);
                }
            }
        }
        LocalCache::forget($metaKey);
    }
}
