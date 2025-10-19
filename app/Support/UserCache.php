<?php

namespace App\Support;

class UserCache
{
    public static function permissionsKey(string $userId): string
    {
        return "user_permissions:{$userId}";
    }

    public static function profileKey(string $userId): string
    {
        return "user_profile:{$userId}";
    }

    public static function forgetPermissions(string|array $userIds): void
    {
        foreach ((array) $userIds as $id) {
            LocalCache::forget(self::permissionsKey($id));
            MenuCache::forgetRenderedForUser($id);
        }
    }

    public static function rememberProfile(string $userId, callable $callback, int $seconds = 120): array
    {
        $value = LocalCache::remember(self::profileKey($userId), $seconds, function () use ($callback) {
            $result = $callback();
            if (is_object($result)) {
                return (array) $result;
            }

            return (array) ($result ?? []);
        });

        return (array) $value;
    }

    public static function forgetProfiles(string|array $userIds): void
    {
        foreach ((array) $userIds as $id) {
            LocalCache::forget(self::profileKey($id));
        }
    }

    public static function getPermissions(string $userId): ?array
    {
        $cached = LocalCache::get(self::permissionsKey($userId));
        return is_array($cached) ? $cached : null;
    }

    public static function putPermissions(string $userId, array $map, int $seconds = 600): void
    {
        LocalCache::put(self::permissionsKey($userId), $map, $seconds);
    }
}
