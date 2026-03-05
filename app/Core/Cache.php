<?php

namespace WCO\Starter\Core;

class Cache
{
    public static function remember(string $key, int $ttl, callable $resolver, string $group = 'wco-starter')
    {
        $cacheKey = self::cache_key($key);
        $found = false;
        $cached = wp_cache_get($cacheKey, $group, false, $found);

        if ($found) {
            return $cached;
        }

        $transientKey = self::transient_key($group, $cacheKey);
        $transient = get_transient($transientKey);
        if (is_array($transient) && array_key_exists('value', $transient)) {
            wp_cache_set($cacheKey, $transient['value'], $group, $ttl);
            return $transient['value'];
        }

        $value = $resolver();
        wp_cache_set($cacheKey, $value, $group, $ttl);
        set_transient($transientKey, ['value' => $value], $ttl);

        return $value;
    }

    public static function forget(string $key, string $group = 'wco-starter'): void
    {
        $cacheKey = self::cache_key($key);
        wp_cache_delete($cacheKey, $group);
        delete_transient(self::transient_key($group, $cacheKey));
    }

    private static function transient_key(string $group, string $key): string
    {
        return sanitize_key($group . '_' . $key);
    }

    private static function cache_key(string $key): string
    {
        return sanitize_key($key);
    }
}
