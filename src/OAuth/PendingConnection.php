<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

/**
 * State after a successful OAuth callback: the merchant is authorized and has to pick a payment service.
 */
final class PendingConnection
{
    /**
     * @param PaymentService[] $services
     */
    public function __construct(
        public readonly string $accountEmail,
        public readonly array $services,
        public readonly int $expiresAt
    ) {
    }

    public function findService(string $serviceId): ?PaymentService
    {
        foreach ($this->services as $service) {
            if ($service->id === $serviceId) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Service to preselect: the one whose URL matches the shop, otherwise the first one.
     */
    public function suggestServiceId(string $shopUrl): ?string
    {
        foreach ($this->services as $service) {
            if ($service->matchesShopUrl($shopUrl)) {
                return $service->id;
            }
        }

        return $this->services[0]->id ?? null;
    }
}
