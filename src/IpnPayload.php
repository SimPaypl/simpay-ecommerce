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
     * Event amount.
     *
     * Transaction events: amount in the currency the shop created the transaction in
     * (amount.original_value, falls back to amount.final_value) - compare it against the order total.
     * Refund events: refunded amount (amount.value).
     */
    public function getAmount(): float
    {
        $amount = $this->data['amount'] ?? [];

        return (float) ($amount['original_value'] ?? $amount['final_value'] ?? $amount['value'] ?? 0);
    }

    /**
     * Event currency: amount.original_currency (transactions, falls back to amount.final_currency)
     * or amount.currency (refunds).
     */
    public function getCurrency(): string
    {
        $amount = $this->data['amount'] ?? [];

        return (string) ($amount['original_currency'] ?? $amount['final_currency'] ?? $amount['currency'] ?? '');
    }

    /**
     * Amount sent by the shop (amount.original_value), or null if not present.
     */
    public function getOriginalAmount(): ?float
    {
        if (!isset($this->data['amount']['original_value'])) {
            return null;
        }
        return (float) $this->data['amount']['original_value'];
    }

    /**
     * Currency sent by the shop (amount.original_currency), or null if not present.
     */
    public function getOriginalCurrency(): ?string
    {
        return isset($this->data['amount']['original_currency']) ? (string) $this->data['amount']['original_currency'] : null;
    }

    /**
     * Amount after conversion, as settled by SimPay (amount.final_value), or null if not present.
     */
    public function getFinalAmount(): ?float
    {
        if (!isset($this->data['amount']['final_value'])) {
            return null;
        }
        return (float) $this->data['amount']['final_value'];
    }

    /**
     * Currency after conversion (amount.final_currency), or null if not present.
     */
    public function getFinalCurrency(): ?string
    {
        return isset($this->data['amount']['final_currency']) ? (string) $this->data['amount']['final_currency'] : null;
    }

    /**
     * Returns the amount to compare against the order total.
     *
     * @deprecated Use getAmount() (and compare getCurrency() with the order currency).
     *             The order currency no longer matters: original_value is always in the shop's currency.
     */
    public function getEffectiveAmount(string $orderCurrency = ''): float
    {
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
