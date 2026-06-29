<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * Fluent builder for transaction creation payload.
 *
 * Provides a clean, type-safe way to construct the transaction payload
 * regardless of the e-commerce platform.
 *
 * Usage:
 *   $payload = TransactionBuilder::create()
 *       ->setAmount(99.99, 'PLN')
 *       ->setDescription('Order #123')
 *       ->setControl('123')
 *       ->setCustomer('Jan Kowalski', 'jan@example.com')
 *       ->setReturnUrls('https://shop.pl/success', 'https://shop.pl/failure')
 *       ->toArray();
 */
final class TransactionBuilder
{
    private float $amount = 0.0;
    private string $currency = 'PLN';
    private string $description = '';
    private string $control = '';
    private ?string $directChannel = null;

    // Customer
    private ?string $customerName = null;
    private ?string $customerEmail = null;
    private ?string $customerIp = null;
    private ?string $customerCountryCode = null;

    // Antifraud
    private ?string $antifraudSystemId = null;
    private ?string $antifraudUserAgent = null;

    // Returns
    private ?string $returnSuccess = null;
    private ?string $returnFailure = null;
    private ?string $returnPending = null;

    // Commission mode
    private string $commissionMode = 'merchant';

    // Billing
    private ?array $billing = null;

    // Shipping
    private ?array $shipping = null;

    // Context (customer purchase history)
    private ?array $context = null;

    // Cart items
    private array $cartItems = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Core fields
    // ──────────────────────────────────────────────────────────────────────

    public function setAmount(float $amount, string $currency = 'PLN'): self
    {
        $this->amount = $amount;
        $this->currency = $currency;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setControl(string $control): self
    {
        $this->control = $control;
        return $this;
    }

    public function setDirectChannel(string $channel): self
    {
        $this->directChannel = $channel;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Customer
    // ──────────────────────────────────────────────────────────────────────

    public function setCustomer(string $name, string $email, ?string $ip = null, ?string $countryCode = null): self
    {
        $this->customerName = mb_substr(trim($name), 0, 64);
        $this->customerEmail = mb_substr(trim($email), 0, 64);
        $this->customerIp = $ip;
        $this->customerCountryCode = $countryCode;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Antifraud
    // ──────────────────────────────────────────────────────────────────────

    public function setAntifraud(?string $systemId = null, ?string $userAgent = null): self
    {
        $this->antifraudSystemId = $systemId;
        $this->antifraudUserAgent = $userAgent;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Return URLs
    // ──────────────────────────────────────────────────────────────────────

    public function setReturnUrls(string $successUrl, string $failureUrl, ?string $pendingUrl = null): self
    {
        $this->returnSuccess = $successUrl;
        $this->returnFailure = $failureUrl;
        $this->returnPending = $pendingUrl;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Commission mode
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Set commission mode: 'merchant' (default) or 'payer'.
     */
    public function setCommissionMode(string $mode): self
    {
        $this->commissionMode = $mode;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Address (Billing / Shipping)
    // ──────────────────────────────────────────────────────────────────────

    public function setBilling(
        ?string $name = null,
        ?string $surname = null,
        ?string $street = null,
        ?string $building = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = null,
        ?string $company = null
    ): self {
        $this->billing = $this->filterNullable([
            'name' => $name ? mb_substr($name, 0, 64) : null,
            'surname' => $surname ? mb_substr($surname, 0, 64) : null,
            'street' => $street ? mb_substr($street, 0, 64) : null,
            'building' => $building ? mb_substr($building, 0, 16) : null,
            'city' => $city ? mb_substr($city, 0, 64) : null,
            'postalCode' => $postalCode ? mb_substr($postalCode, 0, 64) : null,
            'country' => $country,
            'company' => $company ? mb_substr($company, 0, 64) : null,
        ]);
        return $this;
    }

    public function setShipping(
        ?string $name = null,
        ?string $surname = null,
        ?string $street = null,
        ?string $building = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = null,
        ?string $company = null
    ): self {
        $this->shipping = $this->filterNullable([
            'name' => $name ? mb_substr($name, 0, 64) : null,
            'surname' => $surname ? mb_substr($surname, 0, 64) : null,
            'street' => $street ? mb_substr($street, 0, 64) : null,
            'building' => $building ? mb_substr($building, 0, 16) : null,
            'city' => $city ? mb_substr($city, 0, 64) : null,
            'postalCode' => $postalCode ? mb_substr($postalCode, 0, 64) : null,
            'country' => $country,
            'company' => $company ? mb_substr($company, 0, 64) : null,
        ]);
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Context (customer purchase history for antifraud)
    // ──────────────────────────────────────────────────────────────────────

    public function setContext(
        ?string $accountCreatedAt = null,
        ?int $salesTotalCount = null,
        ?float $salesTotalAmount = null,
        ?float $salesAvgAmount = null,
        ?float $salesMaxAmount = null,
        ?string $accountSetCurrency = null,
        ?bool $hasPreviousPurchases = null
    ): self {
        $this->context = $this->filterNullable([
            'accountCreatedAt' => $accountCreatedAt,
            'salesTotalCount' => $salesTotalCount,
            'salesTotalAmount' => $salesTotalAmount !== null ? round($salesTotalAmount, 2) : null,
            'salesAvgAmount' => $salesAvgAmount !== null ? round($salesAvgAmount, 2) : null,
            'salesMaxAmount' => $salesMaxAmount !== null ? round($salesMaxAmount, 2) : null,
            'accountSetCurrency' => $accountSetCurrency,
            'hasPreviousPurchases' => $hasPreviousPurchases,
        ]);
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Cart items
    // ──────────────────────────────────────────────────────────────────────

    public function addCartItem(string $name, int $quantity, float $unitPrice, ?string $producer = null): self
    {
        $item = [
            'name' => mb_substr($name, 0, 255),
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
        ];

        if ($producer !== null) {
            $item['producer'] = mb_substr($producer, 0, 255);
        }

        $this->cartItems[] = $item;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Build
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build the final payload array for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'control' => $this->control,
        ];

        // Customer
        $customer = $this->filterNullable([
            'name' => $this->customerName,
            'email' => $this->customerEmail,
            'ip' => $this->customerIp,
            'countryCode' => $this->customerCountryCode,
        ]);
        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        // Antifraud
        $antifraud = $this->filterNullable([
            'systemId' => $this->antifraudSystemId,
            'useragent' => $this->antifraudUserAgent,
        ]);
        if ($antifraud !== []) {
            $payload['antifraud'] = $antifraud;
        }

        // Returns
        if ($this->returnSuccess !== null && $this->returnFailure !== null) {
            $returns = [
                'success' => $this->returnSuccess,
                'failure' => $this->returnFailure,
            ];
            if ($this->returnPending !== null) {
                $returns['pending'] = $this->returnPending;
            }
            $payload['returns'] = $returns;
        }

        // Commission mode
        $payload['commissionMode'] = $this->commissionMode;

        // Direct channel
        if ($this->directChannel !== null) {
            $payload['directChannel'] = $this->directChannel;
        }

        // Billing
        if ($this->billing !== null && $this->billing !== []) {
            $payload['billing'] = $this->billing;
        }

        // Shipping
        if ($this->shipping !== null && $this->shipping !== []) {
            $payload['shipping'] = $this->shipping;
        }

        // Context
        if ($this->context !== null && $this->context !== []) {
            $payload['context'] = $this->context;
        }

        // Cart
        if ($this->cartItems !== []) {
            $payload['cart'] = $this->cartItems;
        }

        return $payload;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Remove null and empty-string values, preserving false, 0, and 0.0.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterNullable(array $data): array
    {
        return array_filter(
            $data,
            static fn($value): bool => $value !== null && $value !== ''
        );
    }
}

