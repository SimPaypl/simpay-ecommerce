<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\Http\CurlHttpClient;
use SimPay\SDK\Http\HttpClientInterface;

/**
 * Low-level SimPay OAuth 2.0 client (Authorization Code + PKCE, public client).
 *
 * Covers the OAuth endpoints (/oauth/authorize, /oauth/token) and the OAuth API v1
 * used during onboarding. For the full connect flow use OAuthConnector.
 */
final class OAuthClient
{
    public const DEFAULT_BASE_URL = 'https://panel.simpay.pl';

    public const SCOPES = [
        'user:read',
        'payment_services',
        'payment_services:ipn',
        'api_keys:manage',
    ];

    private const API_KEY_NAME_MAX_LENGTH = 32;

    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $clientId,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

    /**
     * @return array{verifier: string, challenge: string}
     */
    public static function generatePkce(): array
    {
        $verifier = self::base64Url(random_bytes(32));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    public static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * SimPay accepts arbitrary redirect_uri for plugin clients only when it is https
     * (or http on localhost / 127.0.0.1).
     */
    public static function isRedirectUriAllowed(string $redirectUri): bool
    {
        $parts = parse_url($redirectUri);
        if (!is_array($parts) || empty($parts['host']) || isset($parts['user'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1'], true);
    }

    /**
     * @param string[] $scopes
     */
    public function getAuthorizationUrl(string $redirectUri, string $state, string $codeChallenge, array $scopes = self::SCOPES): string
    {
        return $this->baseUrl . '/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, expires_in: int, refresh_token: ?string}
     */
    public function exchangeCode(string $code, string $redirectUri, string $codeVerifier): array
    {
        $data = $this->request('POST', $this->baseUrl . '/oauth/token', null, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        if (empty($data['access_token'])) {
            throw new ApiException(500, 'Missing access_token in OAuth token response.');
        }

        return [
            'access_token' => (string) $data['access_token'],
            'expires_in' => (int) ($data['expires_in'] ?? 300),
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
        ];
    }

    /**
     * @return array{id: string, email: string}
     */
    public function getMe(string $accessToken): array
    {
        $data = $this->api('GET', '/me', $accessToken)['data'] ?? [];

        return [
            'id' => (string) ($data['id'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
        ];
    }

    /**
     * @return PaymentService[]
     */
    public function getPaymentServices(string $accessToken): array
    {
        $services = [];
        foreach ($this->api('GET', '/payment/services', $accessToken)['data'] ?? [] as $service) {
            if (is_array($service) && !empty($service['id'])) {
                $services[] = PaymentService::fromArray($service);
            }
        }

        return $services;
    }

    /**
     * Provisions everything the plugin needs for a payment service:
     * a payment_ecomm API key, the IPN URL and a freshly generated IPN signature key.
     *
     * WARNING: generating a new IPN key invalidates the previous one immediately.
     *
     * @return array{api_key: string, service_id: string, ipn_signature_key: string}
     */
    public function provisionPaymentService(string $accessToken, string $serviceId, string $ipnUrl, string $apiKeyName): array
    {
        $apiKey = $this->api('POST', '/api-keys', $accessToken, [
            'keyType' => 'payment_ecomm',
            'name' => function_exists('mb_substr')
                ? mb_substr($apiKeyName, 0, self::API_KEY_NAME_MAX_LENGTH)
                : substr($apiKeyName, 0, self::API_KEY_NAME_MAX_LENGTH),
            'paymentService' => $serviceId,
        ])['data']['bearer'] ?? null;

        if (!is_string($apiKey) || $apiKey === '') {
            throw new ApiException(500, 'Missing bearer in API key response.');
        }

        $this->api('PUT', '/payment/services/' . rawurlencode($serviceId) . '/ipn/url', $accessToken, [
            'url' => $ipnUrl,
        ]);

        $ipnKey = $this->api('PUT', '/payment/services/' . rawurlencode($serviceId) . '/ipn/key', $accessToken)['data']['ipn_key'] ?? null;

        if (!is_string($ipnKey) || $ipnKey === '') {
            throw new ApiException(500, 'Missing ipn_key in IPN key response.');
        }

        return [
            'api_key' => $apiKey,
            'service_id' => $serviceId,
            'ipn_signature_key' => $ipnKey,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function api(string $method, string $path, string $accessToken, ?array $body = null): array
    {
        return $this->request($method, $this->baseUrl . '/api/oauth/v1' . $path, $accessToken, $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?string $accessToken, ?array $body = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($accessToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $response = $this->httpClient->request($method, $url, $headers, $body);
        $data = $response->toArray();

        if (!$response->isSuccessful()) {
            // OAuth endpoints: {"error": "...", "error_description": "..."}; API v1: {"message": "..."}
            $message = $data['error_description'] ?? $data['message'] ?? $data['error'] ?? null;

            throw new ApiException(
                $response->getStatusCode(),
                is_string($message) ? $message : null,
                isset($data['error']) && is_string($data['error']) ? $data['error'] : null
            );
        }

        return $data;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
