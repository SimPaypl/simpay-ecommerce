<?php

declare(strict_types=1);

namespace SimPay\SDK;

use SimPay\SDK\Exception\IpNotAllowedException;

/**
 * IP Allowlist Service.
 *
 * Validates that incoming IPN requests come from SimPay's servers.
 * Supports caching through a pluggable CacheInterface.
 *
 * Usage:
 *   $service = new IpAllowlistService($client);
 *   $service->validate($_SERVER['REMOTE_ADDR']); // throws IpNotAllowedException
 *
 *   // or with cache:
 *   $service = new IpAllowlistService($client, $myCache);
 */
final class IpAllowlistService
{
    private const CACHE_KEY = 'simpay_ip_allowlist';
    private const CACHE_TTL = 3600; // 1 hour

    private SimPayClient $client;
    private ?CacheInterface $cache;

    public function __construct(SimPayClient $client, ?CacheInterface $cache = null)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    /**
     * Check if IP is allowed. Returns true/false without throwing.
     */
    public function isAllowed(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        try {
            $allowedIps = $this->getAllowedIps();

            if (empty($allowedIps)) {
                // If we can't get the list, allow (fail-open for availability)
                return true;
            }

            return in_array($ip, $allowedIps, true);
        } catch (\Throwable) {
            // Fail-open: if we can't verify, allow the request
            return true;
        }
    }

    /**
     * Validate IP - throws exception if not allowed.
     *
     * @throws IpNotAllowedException
     */
    public function validate(string $ip): void
    {
        if (!$this->isAllowed($ip)) {
            throw new IpNotAllowedException($ip);
        }
    }

    /**
     * @return string[]
     */
    private function getAllowedIps(): array
    {
        // Try cache first
        if ($this->cache !== null) {
            $cached = $this->cache->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $ips = $this->client->getAllowedIps();

        // Store in cache
        if ($this->cache !== null && $ips !== []) {
            $this->cache->set(self::CACHE_KEY, $ips, self::CACHE_TTL);
        }

        return $ips;
    }
}

