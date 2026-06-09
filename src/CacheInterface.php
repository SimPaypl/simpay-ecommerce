<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * Simple cache interface for the SDK.
 *
 * Implement this interface using your platform's caching mechanism:
 * - OpenCart: $this->cache->get/set
 * - PrestaShop: Cache::getInstance()
 * - Magento: CacheInterface
 * - WordPress: wp_cache_get/wp_cache_set
 */
interface CacheInterface
{
    /**
     * Retrieve a value from cache.
     *
     * @param string $key
     * @return mixed Returns null if not found or expired
     */
    public function get(string $key): mixed;

    /**
     * Store a value in cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void;

    /**
     * Remove a value from cache.
     *
     * @param string $key
     */
    public function delete(string $key): void;
}

