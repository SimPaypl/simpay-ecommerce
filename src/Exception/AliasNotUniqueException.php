<?php

declare(strict_types=1);

namespace SimPay\SDK\Exception;

/**
 * Thrown when a BLIK OneClick payment fails because the alias (value + type)
 * is registered in multiple banking apps (ALIAS_NOT_UNIQUE).
 *
 * The customer must choose which banking app to use. Present the alternatives
 * list to the user, then retry with BlikAlias::fromValue(...)->withBlikId($selectedBlikId).
 *
 * @see \SimPay\SDK\BlikAlias::withBlikId()
 */
class AliasNotUniqueException extends ApiException
{
    /** @var array<int, array{value: string, type: string, label: string, blik_id: int}> */
    private array $alternatives;

    /**
     * @param array<int, array{value: string, type: string, label: string, blik_id: int}> $alternatives
     */
    public function __construct(string $apiMessage, array $alternatives, ?\Throwable $previous = null)
    {
        $this->alternatives = $alternatives;

        parent::__construct(400, $apiMessage, 'ALIAS_NOT_UNIQUE', $previous);
    }

    /**
     * Get the list of available banking apps for this alias.
     *
     * Each alternative contains:
     * - value: Alias value
     * - type: Alias type (UID)
     * - label: Banking app name to display to the customer
     * - blik_id: ID to pass via BlikAlias::withBlikId() when retrying
     *
     * @return array<int, array{value: string, type: string, label: string, blik_id: int}>
     */
    public function getAlternatives(): array
    {
        return $this->alternatives;
    }
}

