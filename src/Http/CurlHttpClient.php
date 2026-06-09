<?php

declare(strict_types=1);

namespace SimPay\SDK\Http;

use SimPay\SDK\Exception\HttpException;

/**
 * Default cURL-based HTTP client implementation.
 *
 * Use this when the platform doesn't provide its own HTTP client.
 * Works everywhere where PHP cURL extension is available.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): HttpResponse
    {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new HttpException(
                sprintf('cURL error [%d]: %s', $errno, $error),
                $errno
            );
        }

        return new HttpResponse($httpCode, (string) $response);
    }
}

