<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

/**
 * Payment service returned by GET /api/oauth/v1/payment/services.
 */
final class PaymentService
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $status
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['url'] ?? ''),
            (string) ($data['status'] ?? '')
        );
    }

    /**
     * @return array{id: string, name: string, url: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'status' => $this->status,
        ];
    }

    /** Name for display, falls back to the ID when the service has no name. */
    public function getDisplayName(): string
    {
        return $this->name !== '' ? $this->name : $this->id;
    }

    /** Whether the service URL points to the given shop (ignores scheme, path and "www."). */
    public function matchesShopUrl(string $shopUrl): bool
    {
        $normalize = static fn (string $url): string => (string) preg_replace(
            '/^www\./',
            '',
            strtolower((string) parse_url(str_contains($url, '://') ? $url : 'https://' . $url, PHP_URL_HOST))
        );

        $serviceHost = $normalize($this->url);

        return $serviceHost !== '' && $serviceHost === $normalize($shopUrl);
    }
}
