<?php

declare(strict_types=1);

namespace SimPay\SDK\Http;

/**
 * Framework-agnostic HTTP client interface.
 *
 * Each platform (OpenCart, PrestaShop, Magento, WordPress) should provide
 * its own implementation using the native HTTP library.
 */
interface HttpClientInterface
{
    /**
     * Send an HTTP request and return the decoded response.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $url Full URL
     * @param array<string, string> $headers Request headers
     * @param array<string, mixed>|null $body Request body (will be JSON encoded)
     * @param int $timeout Timeout in seconds
     *
     * @return HttpResponse
     *
     * @throws \SimPay\SDK\Exception\HttpException on transport errors
     */
    public function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): HttpResponse;
}

