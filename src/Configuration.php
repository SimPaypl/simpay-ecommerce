<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * SDK configuration — credentials and platform metadata.
 */
final class Configuration
{
    public const API_BASE_URL = 'https://api.simpay.pl';

    public function __construct(
        private readonly string $bearerToken,
        private readonly string $serviceId,
        private readonly string $signatureKey,
        private readonly string $platform = 'php-sdk',
        private readonly string $platformVersion = '1.0.0',
        private readonly string $locale = 'pl'
    ) {
    }

    public function getBearerToken(): string
    {
        return $this->bearerToken;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getSignatureKey(): string
    {
        return $this->signatureKey;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getPlatformVersion(): string
    {
        return $this->platformVersion;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getBaseUrl(): string
    {
        return self::API_BASE_URL;
    }
}
