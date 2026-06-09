# SimPay E-commerce SDK

Wspólne SDK do obsługi płatności SimPay we wszystkich naszych pluginach e-commerce.

- **Walidacja IPN** — sprawdzenie User-Agent, wymaganych pól, podpisu SHA-256, service_id
- **Walidacja IP** — pobranie listy IP SimPay z API, cache, porównanie
- **Porównywanie kwot** — float-safe porównanie czy zapłacono wystarczająco
- **Klient API** — tworzenie transakcji, refundy, BLIK, kanały płatności
- **Builder transakcji** — budowanie payloadu z walidacją pól

## Instalacja

W `composer.json` pluginu:

```json
{
    "repositories": [
        { "type": "path", "url": "../simpay-ecommerce" }
    ],
    "require": {
        "simpay/ecommerce": "*"
    }
}
```

## Struktura

```
src/
├── SimPay.php              # Główna fasada — punkt wejścia
├── SimPayClient.php        # Klient HTTP do API (transakcje, refundy, BLIK, kanały, IP)
├── Configuration.php       # Credentials + metadata platformy
├── IpnValidator.php        # Walidacja webhooków (wersja UA, pola, podpis, service_id)
├── IpnPayload.php          # DTO wyniku walidacji IPN
├── IpAllowlistService.php  # Walidacja IP z opcjonalnym cache
├── TransactionBuilder.php  # Fluent builder payloadu transakcji
├── AmountVerifier.php      # Porównywanie kwot (float-safe)
├── PaymentStatus.php       # Stałe statusów (transaction_paid, transaction_expired, etc.)
├── CacheInterface.php      # Interfejs cache — implementuj per-platforma
├── Http/
│   ├── HttpClientInterface.php
│   ├── HttpResponse.php
│   └── CurlHttpClient.php  # Domyślna implementacja
└── Exception/
    ├── SimPayException.php
    ├── ApiException.php
    ├── HttpException.php
    ├── IpnException.php
    └── IpNotAllowedException.php
```

## Użycie

### Inicjalizacja

```php
use SimPay\SDK\SimPay;

$simpay = new SimPay(
    bearerToken: $config->get('simpay_bearer'),
    serviceId: $config->get('simpay_service_id'),
    signatureKey: $config->get('simpay_signature_key'),
    platform: 'opencart',
    platformVersion: VERSION
);
```

### Tworzenie transakcji

```php
$payload = $simpay->transaction()
    ->setAmount((float) $order['total'], $order['currency_code'])
    ->setDescription('Zamówienie #' . $order['order_id'])
    ->setControl((string) $order['order_id'])
    ->setCustomer($name, $email, $ip, $countryIso)
    ->setReturnUrls($successUrl, $failureUrl)
    ->setBilling($firstName, $lastName, $street, $building, $city, $postCode, $countryIso)
    ->toArray();

$response = $simpay->client()->createTransaction($payload);
$redirectUrl = $response['data']['redirectUrl'];
```

### Obsługa IPN (webhook)

```php
use SimPay\SDK\SimPay;
use SimPay\SDK\AmountVerifier;
use SimPay\SDK\PaymentStatus;

$payload = json_decode(file_get_contents('php://input'), true);

try {
    $ipn = $simpay->handleIpn(
        payload: $payload,
        userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
        remoteIp: $_SERVER['REMOTE_ADDR'] ?? null   // null = pomiń sprawdzenie IP
    );
} catch (\SimPay\SDK\Exception\IpNotAllowedException $e) {
    http_response_code(403);
    exit($e->getMessage());
} catch (\SimPay\SDK\Exception\IpnException $e) {
    http_response_code(400);
    exit($e->getMessage());
}

// Rozróżnianie eventów
if ($ipn->isTransactionEvent()) {
    if (!$ipn->isPaid()) {
        exit('OK'); // jeszcze nie opłacone, potwierdź odbiór
    }

    $orderId = $ipn->getControl();
    $paidAmount = $ipn->getEffectiveAmount($orderCurrency);

    if (!AmountVerifier::isAmountSufficient($orderTotal, $paidAmount)) {
        http_response_code(400);
        exit('Amount mismatch');
    }

    // Aktualizuj status zamówienia
}

if ($ipn->isRefundEvent()) {
    // Obsłuż refund
    $refundId = $ipn->data['id'];
    $status = $ipn->getStatus();
}

exit('OK');
```

### Refundy

```php
$simpay->client()->createRefund($transactionId);           // pełny
$simpay->client()->createRefund($transactionId, 50.00);    // częściowy
```

### BLIK Level 0

```php
$simpay->client()->sendBlikLevel0($transactionId, '123456');
```

### Kanały płatności

```php
$channels = $simpay->client()->getChannels();
```

## Adapter HTTP (opcjonalny)

Domyślnie SDK używa cURL. Jeśli platforma wymaga innego klienta HTTP:

```php
use SimPay\SDK\Http\HttpClientInterface;
use SimPay\SDK\Http\HttpResponse;

class WordPressHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): HttpResponse
    {
        $response = wp_remote_request($url, [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => $timeout,
            'body'    => $body !== null ? json_encode($body) : null,
        ]);

        if (is_wp_error($response)) {
            throw new \SimPay\SDK\Exception\HttpException($response->get_error_message());
        }

        return new HttpResponse(
            wp_remote_retrieve_response_code($response),
            wp_remote_retrieve_body($response)
        );
    }
}

$simpay = new SimPay($bearer, $serviceId, $signatureKey, 'wordpress', $version, new WordPressHttpClient());
```

## Adapter Cache (opcjonalny)

Przyspiesza walidację IP — zamiast odpytywać API przy każdym IPN:

```php
use SimPay\SDK\CacheInterface;

class PrestaShopCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        $val = \Cache::getInstance()->get($key);
        return $val !== false ? $val : null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        \Cache::getInstance()->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        \Cache::getInstance()->delete($key);
    }
}

$simpay = new SimPay($bearer, $serviceId, $signatureKey, 'prestashop', _PS_VERSION_, null, new PrestaShopCache());
```

## Stałe statusów

```php
use SimPay\SDK\PaymentStatus;

PaymentStatus::isPaid($status);    // transaction_paid | transaction_confirmed
PaymentStatus::isFinal($status);   // paid, confirmed, canceled, expired, failure, fraud, refunded
PaymentStatus::isFailed($status);  // canceled, expired, failure, fraud

// Stałe do użycia w match/switch:
PaymentStatus::TRANSACTION_PAID
PaymentStatus::TRANSACTION_CANCELED
PaymentStatus::TRANSACTION_EXPIRED
PaymentStatus::TRANSACTION_FAILURE
PaymentStatus::TRANSACTION_FRAUD
PaymentStatus::TRANSACTION_REFUNDED
```

