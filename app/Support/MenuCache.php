<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class MenuCache
{
    protected static function key(string $companyId, string $menuKey): string
    {
        return "menu:data:{$companyId}:{$menuKey}";
    }

    protected static function renderKey(string $companyId, ?string $userId, string $menuKey): string
    {
        $userPart = $userId ? $userId : 'guest';
        return "menu:render:{$companyId}:{$menuKey}:{$userPart}";
    }

    protected static function renderMenuIndexKey(string $companyId, string $menuKey): string
    {
        return "menu:render:index:menu:{$companyId}:{$menuKey}";
    }

    protected static function renderUserIndexKey(string $userId): string
    {
        return "menu:render:index:user:{$userId}";
    }

    protected static function renderMetaKey(string $cacheKey): string
    {
        return "menu:render:meta:{$cacheKey}";
    }

    public static function remember(string $companyId, string $menuKey, callable $callback, int $seconds = 300): mixed
    {
        return LocalCache::remember(self::key($companyId, $menuKey), $seconds, $callback);
    }

    public static function rememberRendered(string $companyId, ?string $userId, string $menuKey, callable $callback, int $seconds = 120): array
    {
        $cacheKey = self::renderKey($companyId, $userId, $menuKey);

        return LocalCache::remember($cacheKey, $seconds, function () use ($callback, $companyId, $menuKey, $cacheKey, $userId) {
            $value = $callback();
            self::registerRenderedKey($companyId, $menuKey, $cacheKey);
            if ($userId) {
                self::registerRenderedUserKey($userId, $cacheKey);
            }
            LocalCache::put(self::renderMetaKey($cacheKey), [
                'user_id' => $userId,
                'company_id' => $companyId,
                'menu_key' => $menuKey,
            ], 86400);
            return $value;
        });
    }

    public static function forget(string $companyId, string $menuKey): void
    {
        LocalCache::forget(self::key($companyId, $menuKey));
        self::forgetRenderedForMenu($companyId, $menuKey);
    }

    public static function forgetByMenuId(string $menuId): void
    {
        $menu = DB::table('menus')->select('company_id', 'key')->where('id', $menuId)->first();
        if ($menu) {
            self::forget($menu->company_id, $menu->key);
        }
    }

    public static function forgetRenderedForMenu(string $companyId, string $menuKey): void
    {
        $indexKey = self::renderMenuIndexKey($companyId, $menuKey);
        $keys = LocalCache::get($indexKey, []);
        foreach ($keys as $key) {
            LocalCache::forget($key);
            self::forgetRenderedMeta($key);
        }
        LocalCache::forget($indexKey);
    }

    public static function forgetRenderedForUser(string $userId): void
    {
        $indexKey = self::renderUserIndexKey($userId);
        $keys = LocalCache::get($indexKey, []);
        foreach ($keys as $key) {
            LocalCache::forget($key);
            self::forgetRenderedMeta($key);
        }
        LocalCache::forget($indexKey);
    }

    protected static function registerRenderedKey(string $companyId, string $menuKey, string $cacheKey): void
    {
        $indexKey = self::renderMenuIndexKey($companyId, $menuKey);
        $keys = LocalCache::get($indexKey, []);
        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            LocalCache::put($indexKey, $keys, 86400);
        }
    }

    protected static function registerRenderedUserKey(string $userId, string $cacheKey): void
    {
        $indexKey = self::renderUserIndexKey($userId);
        $keys = LocalCache::get($indexKey, []);
        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            LocalCache::put($indexKey, $keys, 86400);
        }
    }

    protected static function forgetRenderedMeta(string $cacheKey): void
    {
        $metaKey = self::renderMetaKey($cacheKey);
        $meta = LocalCache::get($metaKey, []);
        if (isset($meta['user_id']) && $meta['user_id']) {
            $userIndex = self::renderUserIndexKey($meta['user_id']);
            $keys = LocalCache::get($userIndex, []);
            if ($keys) {
                $filtered = array_values(array_filter($keys, fn ($k) => $k !== $cacheKey));
                if ($filtered) {
                    LocalCache::put($userIndex, $filtered, 86400);
                } else {
                    LocalCache::forget($userIndex);
                }
            }
        }

        if (isset($meta['company_id'], $meta['menu_key'])) {
            $menuIndex = self::renderMenuIndexKey($meta['company_id'], $meta['menu_key']);
            $keys = LocalCache::get($menuIndex, []);
            if ($keys) {
                $filtered = array_values(array_filter($keys, fn ($k) => $k !== $cacheKey));
                if ($filtered) {
                    LocalCache::put($menuIndex, $filtered, 86400);
                } else {
                    LocalCache::forget($menuIndex);
                }
            }
        }
        LocalCache::forget($metaKey);
    }
}
