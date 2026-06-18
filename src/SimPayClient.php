<?php

declare(strict_types=1);

namespace SimPay\SDK;

use SimPay\SDK\Exception\AliasNotUniqueException;
use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\Http\CurlHttpClient;
use SimPay\SDK\Http\HttpClientInterface;
use SimPay\SDK\Http\HttpResponse;

/**
 * SimPay API client.
 *
 * Endpoints: transactions, refunds, BLIK, channels, IP allowlist.
 */
final class SimPayClient
{
    private Configuration $config;
    private HttpClientInterface $httpClient;

    public function __construct(Configuration $config, ?HttpClientInterface $httpClient = null)
    {
        $this->config = $config;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Transactions
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a new payment transaction.
     *
     * @param array<string, mixed> $payload Payload from TransactionBuilder::toArray()
     * @return array<string, mixed> API response (data.redirectUrl, data.transactionId, etc.)
     */
    public function createTransaction(array $payload): array
    {
        $url = sprintf('%s/payment/%s/transactions', $this->config->getBaseUrl(), $this->config->getServiceId());
        return $this->post($url, $payload);
    }

    /**
     * Get transaction details.
     */
    public function getTransaction(string $transactionId): array
    {
        $url = sprintf(
            '%s/payment/%s/transactions/%s',
            $this->config->getBaseUrl(),
            $this->config->getServiceId(),
            rawurlencode($transactionId)
        );
        return $this->get($url);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Refunds
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a refund.
     *
     * @param string $transactionId
     * @param float|null $amount Partial refund amount (null = full refund)
     */
    public function createRefund(string $transactionId, ?float $amount = null): array
    {
        $url = sprintf(
            '%s/payment/%s/transactions/%s/refunds',
            $this->config->getBaseUrl(),
            $this->config->getServiceId(),
            rawurlencode($transactionId)
        );

        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        return $this->post($url, $payload);
    }

    // ──────────────────────────────────────────────────────────────────────
    // BLIK
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Send BLIK Level 0 code.
     *
     * Optionally pass a BlikAlias to register a OneClick alias during the first payment.
     * After successful registration, listen for the `blik:alias_status_changed` IPN event.
     *
     * @param string        $transactionId
     * @param string        $blikCode       6-digit BLIK code
     * @param BlikAlias|null $alias         Alias to register (use BlikAlias::register())
     * @return array<string, mixed>
     */
    public function sendBlikLevel0(string $transactionId, string $blikCode, ?BlikAlias $alias = null): array
    {
        $url = sprintf(
            '%s/payment/%s/blik/level0/%s',
            $this->config->getBaseUrl(),
            $this->config->getServiceId(),
            rawurlencode($transactionId)
        );

        $payload = [
            'ticket' => ['T6' => $blikCode],
        ];

        if ($alias !== null) {
            $payload['alias'] = $alias->toArray();
        }

        return $this->post($url, $payload);
    }

    /**
     * Send a BLIK OneClick payment (without a 6-digit code).
     *
     * Requires an active alias — either identified by SimPay UUID
     * (BlikAlias::fromUuid) or by your own value+type (BlikAlias::fromValue).
     *
     * On success the API returns HTTP 204 (empty body) — the customer receives
     * a push notification in their banking app to authorize the payment.
     *
     * If the alias matches multiple banking apps (only with value+type method),
     * an AliasNotUniqueException is thrown containing the alternatives list.
     * Present the list to the user, then retry with BlikAlias::withBlikId().
     *
     * @param string   $transactionId
     * @param BlikAlias $alias
     * @return array<string, mixed> Empty array on success (HTTP 204)
     *
     * @throws AliasNotUniqueException When alias matches multiple banking apps
     * @throws ApiException            On other API errors
     */
    public function sendBlikOneClick(string $transactionId, BlikAlias $alias): array
    {
        $url = sprintf(
            '%s/payment/%s/blik/level0/%s',
            $this->config->getBaseUrl(),
            $this->config->getServiceId(),
            rawurlencode($transactionId)
        );

        $payload = [
            'alias' => $alias->toArray(),
        ];

        $response = $this->httpClient->request('POST', $url, $this->buildHeaders(), $payload);

        // HTTP 204 No Content — success, push notification sent to the customer
        if ($response->getStatusCode() === 204) {
            return [];
        }

        $data = $response->toArray();

        if ($response->isSuccessful()) {
            return $data;
        }

        // Handle ALIAS_NOT_UNIQUE error
        $errorCode = $data['errorCode'] ?? $data['code'] ?? null;
        if ($response->getStatusCode() === 400 && $errorCode === 'ALIAS_NOT_UNIQUE') {
            throw new AliasNotUniqueException(
                $data['message'] ?? 'Alias is not unique',
                $data['data']['alternatives'] ?? []
            );
        }

        throw new ApiException(
            $response->getStatusCode(),
            $data['message'] ?? null,
            $data['code'] ?? null
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Payment channels
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get available payment channels.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getChannels(): array
    {
        $url = sprintf('%s/payment/%s/channels', $this->config->getBaseUrl(), $this->config->getServiceId());
        $response = $this->get($url);
        return $response['data'] ?? [];
    }

    // ──────────────────────────────────────────────────────────────────────
    // IP Allowlist
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get the list of SimPay server IPs (for IPN validation).
     *
     * @return string[]
     */
    public function getAllowedIps(): array
    {
        $url = $this->config->getBaseUrl() . '/ip';
        $response = $this->get($url);
        $ips = $response['data'] ?? [];

        return is_array($ips) ? array_values(array_filter($ips, 'is_string')) : [];
    }

    // ──────────────────────────────────────────────────────────────────────
    // HTTP internals
    // ──────────────────────────────────────────────────────────────────────

    private function get(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, $this->buildHeaders());
        return $this->handleResponse($response);
    }

    private function post(string $url, array $body): array
    {
        $response = $this->httpClient->request('POST', $url, $this->buildHeaders(), $body);
        return $this->handleResponse($response);
    }

    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config->getBearerToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-SIM-PLATFORM' => $this->config->getPlatform(),
            'X-SIM-PLATFORM-VERSION' => $this->config->getPlatformVersion(),
            'X-SIM-LOCALE' => $this->config->getLocale(),
        ];
    }

    private function handleResponse(HttpResponse $response): array
    {
        $data = $response->toArray();

        if ($response->isSuccessful()) {
            return $data;
        }

        throw new ApiException(
            $response->getStatusCode(),
            $data['message'] ?? null,
            $data['code'] ?? null
        );
    }
}
