<?php

declare(strict_types=1);

namespace SimPay\SDK;

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
     */
    public function sendBlikLevel0(string $transactionId, string $blikCode): array
    {
        $url = sprintf(
            '%s/payment/%s/blik/level0/%s',
            $this->config->getBaseUrl(),
            $this->config->getServiceId(),
            rawurlencode($transactionId)
        );

        return $this->post($url, [
            'ticket' => ['T6' => $blikCode],
        ]);
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
