<?php

declare(strict_types=1);

namespace SimPay\SDK\Exception;

/**
 * Thrown when the SimPay API returns a non-2xx HTTP status code.
 */
class ApiException extends SimPayException
{
    private int $httpStatusCode;
    private ?string $apiMessage;
    private ?string $apiCode;

    public function __construct(int $httpStatusCode, ?string $apiMessage = null, ?string $apiCode = null, ?\Throwable $previous = null)
    {
        $this->httpStatusCode = $httpStatusCode;
        $this->apiMessage = $apiMessage;
        $this->apiCode = $apiCode;

        $message = sprintf(
            'SimPay API error [HTTP %d]: %s',
            $httpStatusCode,
            $apiMessage ?? 'Unknown error'
        );

        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getApiMessage(): ?string
    {
        return $this->apiMessage;
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }
}

