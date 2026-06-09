<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * DTO representing a validated IPN webhook.
 *
 * Does not interpret `data` contents — that's left to the platform,
 * as it may contain a transaction, refund, or something new in the future.
 */
final class IpnPayload
{
    public function __construct(
        /** Event type, e.g. "transaction:status_changed", "transaction_refund:status_changed" */
        public readonly string $type,
        public readonly string $notificationId,
        public readonly string $date,
        /** Raw event data — structure depends on type */
        public readonly array $data
    ) {
    }

    /**
     * Is this a transaction status change event?
     */
    public function isTransactionEvent(): bool
    {
        return $this->type === 'transaction:status_changed';
    }

    /**
     * Is this a refund status change event?
     */
    public function isRefundEvent(): bool
    {
        return $this->type === 'transaction_refund:status_changed';
    }

    /**
     * Status from the data field (e.g. "transaction_paid", "transaction_expired").
     */
    public function getStatus(): string
    {
        return (string) ($this->data['status'] ?? '');
    }

    /**
     * Transaction ID.
     * For transaction events: data.id
     * For refund events: data.transaction.id
     */
    public function getTransactionId(): string
    {
        if ($this->isRefundEvent()) {
            return (string) ($this->data['transaction']['id'] ?? '');
        }

        return (string) ($this->data['id'] ?? '');
    }

    /**
     * Control field (order ID in the shop system).
     */
    public function getControl(): string
    {
        return (string) ($this->data['control'] ?? '');
    }

    /**
     * Transaction amount (amount.value).
     */
    public function getAmount(): float
    {
        return (float) ($this->data['amount']['value'] ?? 0);
    }

    /**
     * Transaction currency (amount.currency).
     */
    public function getCurrency(): string
    {
        return (string) ($this->data['amount']['currency'] ?? '');
    }

    /**
     * Original amount (before currency conversion), if available.
     */
    public function getOriginalAmount(): ?float
    {
        if (!isset($this->data['originalAmount']['value'])) {
            return null;
        }
        return (float) $this->data['originalAmount']['value'];
    }

    /**
     * Original currency, if available.
     */
    public function getOriginalCurrency(): ?string
    {
        return $this->data['originalAmount']['currency'] ?? null;
    }

    /**
     * Returns the amount to compare against the order total.
     * If the order is in a non-PLN currency and originalAmount is available — use it.
     */
    public function getEffectiveAmount(string $orderCurrency): float
    {
        if ($orderCurrency !== 'PLN' && $this->getOriginalAmount() !== null) {
            return $this->getOriginalAmount();
        }

        return $this->getAmount();
    }

    /**
     * Check if the status indicates a successful payment.
     */
    public function isPaid(): bool
    {
        return PaymentStatus::isPaid($this->getStatus());
    }
}
