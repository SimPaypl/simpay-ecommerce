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
     * Is this a BLIK alias status change event?
     *
     * Fired when a customer accepts/rejects saving a shop as trusted
     * in their banking app after the first BLIK Level 0 payment with alias registration.
     */
    public function isBlikAliasEvent(): bool
    {
        return $this->type === 'blik:alias_status_changed';
    }

    /**
     * Is this a BLIK Level 0 code/alias authorization status event?
     *
     * Contains low-level technical details from the Polish Payment Standard (PSP)
     * about the BLIK code or alias authorization attempt.
     */
    public function isBlikCodeStatusEvent(): bool
    {
        return $this->type === 'transaction_blik_level0:code_status_changed';
    }

    /**
     * Get the BLIK alias UUID from a blik:alias_status_changed event.
     *
     * Store this UUID in your database when alias status becomes "alias_active".
     * Use it later with BlikAlias::fromUuid() for OneClick payments.
     *
     * @return string|null UUID or null if not an alias event / not present
     */
    public function getAliasId(): ?string
    {
        if (!$this->isBlikAliasEvent()) {
            return null;
        }

        return isset($this->data['id']) ? (string) $this->data['id'] : null;
    }

    /**
     * Get the BLIK alias status from a blik:alias_status_changed event.
     *
     * Possible values: "alias_active", "alias_inactive", "alias_pending".
     * Only proceed with OneClick when status is "alias_active".
     *
     * @return string|null Alias status or null if not an alias event
     */
    public function getAliasStatus(): ?string
    {
        if (!$this->isBlikAliasEvent()) {
            return null;
        }

        return isset($this->data['status']) ? (string) $this->data['status'] : null;
    }

    /**
     * Is the BLIK alias active and ready for OneClick payments?
     */
    public function isAliasActive(): bool
    {
        return $this->getAliasStatus() === PaymentStatus::ALIAS_ACTIVE;
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
