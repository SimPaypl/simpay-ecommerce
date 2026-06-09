<?php

declare(strict_types=1);

namespace SimPay\SDK;

use SimPay\SDK\Exception\IpnException;

/**
 * Validates incoming IPN webhooks from SimPay.
 *
 * Checks:
 * - IPN version from User-Agent (SimPay-IPN/2.0)
 * - Required fields (type, notification_id, date, data, signature)
 * - SHA-256 signature
 * - service_id
 */
final class IpnValidator
{
    private const SUPPORTED_VERSION = '2.0';

    private string $serviceId;
    private string $signatureKey;

    public function __construct(string $serviceId, string $signatureKey)
    {
        $this->serviceId = $serviceId;
        $this->signatureKey = $signatureKey;
    }

    /**
     * Validate IPN payload and return parsed result.
     *
     * @param array $payload Decoded JSON from php://input
     * @param string|null $userAgent User-Agent header value (null = skip version check)
     * @return IpnPayload
     *
     * @throws IpnException
     */
    public function validate(array $payload, ?string $userAgent = null): IpnPayload
    {
        // 1. Check IPN version from User-Agent
        if ($userAgent !== null) {
            $this->checkVersion($userAgent);
        }

        // 2. Check required fields
        foreach (['type', 'notification_id', 'date', 'data', 'signature'] as $field) {
            if (empty($payload[$field])) {
                throw new IpnException('Invalid payload - missing required field: ' . $field);
            }
        }

        // 3. Verify signature
        if (!$this->isValidSignature($payload)) {
            throw new IpnException('Invalid signature');
        }

        // 4. Verify service_id
        $data = $payload['data'];
        if (isset($data['service_id']) && (string) $data['service_id'] !== $this->serviceId) {
            throw new IpnException('Invalid service_id');
        }

        // 5. Build DTO
        return new IpnPayload(
            type: (string) $payload['type'],
            notificationId: (string) $payload['notification_id'],
            date: (string) $payload['date'],
            data: $data
        );
    }

    private function checkVersion(string $userAgent): void
    {
        $parts = explode('/', $userAgent, 2);
        $version = $parts[1] ?? '';

        if ($version !== self::SUPPORTED_VERSION) {
            throw new IpnException('IPN version is not supported (v: ' . ($version ?: 'N/A') . ')');
        }
    }

    private function isValidSignature(array $payload): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        unset($payload['signature']);

        $flat = $this->flattenArray($payload);
        $flat[] = $this->signatureKey;

        $computed = hash('sha256', implode('|', $flat));

        return hash_equals($computed, $signature);
    }

    /**
     * @return string[]
     */
    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, static function ($value) use (&$result): void {
            $result[] = (string) $value;
        });
        return $result;
    }
}
