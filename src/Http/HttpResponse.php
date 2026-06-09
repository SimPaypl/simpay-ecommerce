<?php

declare(strict_types=1);

namespace SimPay\SDK\Http;

/**
 * Immutable HTTP response value object.
 */
final class HttpResponse
{
    private int $statusCode;
    private string $body;
    private ?array $decoded;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->decoded = null;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->decoded === null) {
            $this->decoded = json_decode($this->body, true);
            if (!is_array($this->decoded)) {
                $this->decoded = [];
            }
        }

        return $this->decoded;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}

