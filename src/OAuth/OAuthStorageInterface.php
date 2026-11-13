<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

/**
 * Per-user, short-lived storage for the OAuth connect flow (PKCE verifier, state, temporary access token).
 *
 */
interface OAuthStorageInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void;

    public function remove(string $key): void;
}
