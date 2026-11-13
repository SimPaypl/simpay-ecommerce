<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\Http\HttpClientInterface;

/**
 * Platform-agnostic "Connect with SimPay" flow for e-commerce plugins.
 *
 * OAuth is used only for onboarding: the access token lives 5 minutes and plugin clients get no
 * refresh token, so the connector provisions a regular payment_ecomm API key + IPN key which the
 * plugin then uses exactly like manually entered credentials.
 *
 * Platform responsibilities: routing (3 admin endpoints), storage (session), saving the result,
 * translating OAuthException::getReason() and rendering the service picker.
 *
 *   // 1. "Connect" button
 *   $redirect = $connector->start($callbackUrl);              // -> redirect the browser there
 *
 *   // 2. Callback endpoint ($callbackUrl)
 *   $pending = $connector->handleCallback($_GET);              // -> show service picker
 *
 *   // 3. Service picker submit
 *   $result = $connector->connect($serviceId, $ipnUrl, 'WooCommerce ' . $host);
 *   // save $result->apiKey, $result->serviceId, $result->ipnSignatureKey
 */
final class OAuthConnector
{
    private const FLOW_KEY = 'simpay_oauth_flow';
    private const PENDING_KEY = 'simpay_oauth_pending';

    /** Seconds subtracted from token lifetime so provisioning never starts with an almost expired token. */
    private const TOKEN_SAFETY_MARGIN = 20;

    private OAuthClient $client;
    private OAuthStorageInterface $storage;

    public function __construct(OAuthClient $client, OAuthStorageInterface $storage)
    {
        $this->client = $client;
        $this->storage = $storage;
    }

    public static function create(
        string $clientId,
        OAuthStorageInterface $storage,
        ?HttpClientInterface $httpClient = null,
        string $baseUrl = OAuthClient::DEFAULT_BASE_URL
    ): self {
        return new self(new OAuthClient($clientId, $baseUrl, $httpClient), $storage);
    }

    /**
     * Use to decide whether to show the "Connect" button at all.
     */
    public function isRedirectUriAllowed(string $redirectUri): bool
    {
        return OAuthClient::isRedirectUriAllowed($redirectUri);
    }

    /**
     * Starts a new connect flow and returns the SimPay authorization URL to redirect the browser to.
     *
     * $redirectUri must be absolute, https (or localhost) and point to the platform callback endpoint.
     * Build it from the shop's configured domain, not from the current request - behind a TLS-terminating
     * proxy the request looks like http.
     *
     * @throws OAuthException REDIRECT_URI_NOT_ALLOWED
     */
    public function start(string $redirectUri): string
    {
        if (!OAuthClient::isRedirectUriAllowed($redirectUri)) {
            throw new OAuthException(
                OAuthException::REDIRECT_URI_NOT_ALLOWED,
                'OAuth redirect_uri must use https (or localhost): ' . strtok($redirectUri, '?')
            );
        }

        $pkce = OAuthClient::generatePkce();
        $state = OAuthClient::generateState();

        $this->storage->remove(self::PENDING_KEY);
        $this->storage->set(self::FLOW_KEY, [
            'state' => $state,
            'verifier' => $pkce['verifier'],
            'redirect_uri' => $redirectUri,
        ]);

        return $this->client->getAuthorizationUrl($redirectUri, $state, $pkce['challenge']);
    }

    /**
     * Handles the redirect back from SimPay: verifies state, exchanges the code for a token
     * and loads the merchant's payment services.
     *
     * @param array<string, mixed> $query Callback query parameters ($_GET or equivalent)
     *
     * @throws OAuthException ACCESS_DENIED | AUTHORIZATION_FAILED | INVALID_STATE | NO_PAYMENT_SERVICES
     * @throws ApiException|\SimPay\SDK\Exception\HttpException on API / transport errors
     */
    public function handleCallback(array $query): PendingConnection
    {
        $flow = $this->storage->get(self::FLOW_KEY);
        $this->storage->remove(self::FLOW_KEY); // single use

        if (isset($query['error'])) {
            $error = (string) $query['error'];
            $description = isset($query['error_description']) ? (string) $query['error_description'] : null;

            throw new OAuthException(
                $error === 'access_denied' ? OAuthException::ACCESS_DENIED : OAuthException::AUTHORIZATION_FAILED,
                sprintf('SimPay authorization error: %s%s', $error, $description ? ' - ' . $description : ''),
                $description ?? $error
            );
        }

        $code = (string) ($query['code'] ?? '');
        $state = (string) ($query['state'] ?? '');

        if ($flow === null || $code === '' || !hash_equals((string) ($flow['state'] ?? ''), $state)) {
            throw new OAuthException(OAuthException::INVALID_STATE, 'Invalid or missing OAuth state.');
        }

        $token = $this->client->exchangeCode($code, (string) $flow['redirect_uri'], (string) $flow['verifier']);
        $services = $this->client->getPaymentServices($token['access_token']);

        if ($services === []) {
            throw new OAuthException(OAuthException::NO_PAYMENT_SERVICES, 'No payment services on the SimPay account.');
        }

        try {
            $email = $this->client->getMe($token['access_token'])['email'];
        } catch (ApiException) {
            $email = ''; // informational only
        }

        $pending = new PendingConnection(
            $email,
            $services,
            time() + max(0, $token['expires_in'] - self::TOKEN_SAFETY_MARGIN)
        );

        $this->storage->set(self::PENDING_KEY, [
            'access_token' => $token['access_token'],
            'expires_at' => $pending->expiresAt,
            'email' => $pending->accountEmail,
            'services' => array_map(static fn (PaymentService $s): array => $s->toArray(), $services),
        ]);

        return $pending;
    }

    /**
     * Connection waiting for service selection, or null if there is none / the token expired.
     */
    public function getPendingConnection(): ?PendingConnection
    {
        return $this->readPending()['connection'] ?? null;
    }

    /**
     * Provisions the selected service: creates an API key, sets the IPN URL and generates a new IPN key.
     *
     * WARNING: the previous IPN signature key of the service stops working immediately.
     * Each call creates a new API key in the SimPay panel.
     *
     * @param string $apiKeyName Name visible in the SimPay panel (max 32 chars), e.g. "PrestaShop shop.pl"
     *
     * @throws OAuthException EXPIRED | UNKNOWN_SERVICE
     * @throws ApiException|\SimPay\SDK\Exception\HttpException on API / transport errors
     */
    public function connect(string $serviceId, string $ipnUrl, string $apiKeyName): ConnectionResult
    {
        $pending = $this->readPending();

        if ($pending === null) {
            throw new OAuthException(OAuthException::EXPIRED, 'OAuth authorization expired or missing.');
        }

        $service = $pending['connection']->findService($serviceId);

        if ($service === null) {
            // keep the pending connection so the merchant can pick again
            throw new OAuthException(OAuthException::UNKNOWN_SERVICE, 'Unknown payment service: ' . $serviceId);
        }


        return new ConnectionResult(
            $credentials['api_key'],
            $credentials['service_id'],
            $credentials['ipn_signature_key'],
            $service->getDisplayName(),
            $pending['connection']->accountEmail
        );
    }

    /**
     * Aborts the flow (e.g. "Cancel" on the service picker) and forgets the temporary token.
     */
    public function cancel(): void
    {
        $this->storage->remove(self::FLOW_KEY);
        $this->storage->remove(self::PENDING_KEY);
    }

    /**
     * @return array{access_token: string, connection: PendingConnection}|null
     */
    private function readPending(): ?array
    {
        $data = $this->storage->get(self::PENDING_KEY);

        if ($data === null || (int) ($data['expires_at'] ?? 0) < time() || empty($data['access_token'])) {
            $this->storage->remove(self::PENDING_KEY);

            return null;
        }

        $services = array_map(
            static fn ($s): PaymentService => PaymentService::fromArray(is_array($s) ? $s : []),
            is_array($data['services'] ?? null) ? $data['services'] : []
        );

        return [
            'access_token' => (string) $data['access_token'],
            'connection' => new PendingConnection((string) ($data['email'] ?? ''), $services, (int) $data['expires_at']),
        ];
    }
}
