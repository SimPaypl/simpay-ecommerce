<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

/**
 * Credentials provisioned by OAuthConnector::connect() - store them in the platform configuration
 * exactly like manually entered ones (bearer token, service ID, IPN signature key).
 */
final class ConnectionResult
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $serviceId,
        public readonly string $ipnSignatureKey,
        public readonly string $serviceName,
        public readonly string $accountEmail
    ) {
    }
}
