<?php

declare(strict_types=1);

namespace SimPay\SDK;

use SimPay\SDK\Exception\IpnException;
use SimPay\SDK\Exception\IpNotAllowedException;
use SimPay\SDK\Http\CurlHttpClient;
use SimPay\SDK\Http\HttpClientInterface;

/**
 * Main SDK entry point.
 *
 * Usage:
 *
 *   $simpay = new SimPay($bearer, $serviceId, $signatureKey, 'opencart', '4.0.2.1');
 *
 *   // Create transaction
 *   $response = $simpay->client()->createTransaction($payload);
 *
 *   // Handle IPN
 *   $ipn = $simpay->handleIpn($payload, $userAgent, $remoteIp);
 */
final class SimPay
{
    private SimPayClient $client;
    private IpnValidator $ipnValidator;
    private IpAllowlistService $ipAllowlist;

    public function __construct(
        string $bearerToken,
        string $serviceId,
        string $signatureKey,
        string $platform = 'php-sdk',
        string $platformVersion = '1.0.0',
        ?HttpClientInterface $httpClient = null,
        ?CacheInterface $cache = null
    ) {
        $config = new Configuration($bearerToken, $serviceId, $signatureKey, $platform, $platformVersion);
        $this->client = new SimPayClient($config, $httpClient ?? new CurlHttpClient());
        $this->ipnValidator = new IpnValidator($serviceId, $signatureKey);
        $this->ipAllowlist = new IpAllowlistService($this->client, $cache);
    }

    // ──────────────────────────────────────────────────────────────────────
    // API Client — transactions, refunds, BLIK, channels
    // ──────────────────────────────────────────────────────────────────────

    public function client(): SimPayClient
    {
        return $this->client;
    }

    // ──────────────────────────────────────────────────────────────────────
    // IPN — webhook validation
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Validate an incoming IPN webhook.
     *
     * Performs all checks previously duplicated across platforms:
     * - IP allowlist validation (optional)
     * - User-Agent version check
     * - Required fields and signature verification
     *
     * @param array $payload Decoded JSON from php://input
     * @param string|null $userAgent $_SERVER['HTTP_USER_AGENT'] (null = skip version check)
     * @param string|null $remoteIp Requesting IP address (null = skip IP check)
     * @return IpnPayload
     *
     * @throws IpnException
     * @throws IpNotAllowedException
     */
    public function handleIpn(array $payload, ?string $userAgent = null, ?string $remoteIp = null): IpnPayload
    {
        // IP validation
        if ($remoteIp !== null) {
            $this->ipAllowlist->validate($remoteIp);
        }

        return $this->ipnValidator->validate($payload, $userAgent);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Transaction builder
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a new transaction payload builder.
     */
    public function transaction(): TransactionBuilder
    {
        return TransactionBuilder::create();
    }

    // ──────────────────────────────────────────────────────────────────────
    // IP Allowlist
    // ──────────────────────────────────────────────────────────────────────

    public function ipAllowlist(): IpAllowlistService
    {
        return $this->ipAllowlist;
    }
}
