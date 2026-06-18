<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * Transaction and refund status constants.
 *
 * Values match what the SimPay API actually sends in IPN webhooks.
 */
final class PaymentStatus
{
    // Transaction statuses
    public const TRANSACTION_NEW = 'transaction_new';
    public const TRANSACTION_GENERATED = 'transaction_generated';
    public const TRANSACTION_PAID = 'transaction_paid';
    public const TRANSACTION_CONFIRMED = 'transaction_confirmed';
    public const TRANSACTION_CANCELED = 'transaction_canceled';
    public const TRANSACTION_EXPIRED = 'transaction_expired';
    public const TRANSACTION_FAILURE = 'transaction_failure';
    public const TRANSACTION_FRAUD = 'transaction_fraud';
    public const TRANSACTION_REFUNDED = 'transaction_refunded';

    // Refund statuses
    public const REFUND_NEW = 'refund_new';
    public const REFUND_COMPLETED = 'refund_completed';
    public const REFUND_FAILED = 'refund_failed';

    // IPN event types
    public const TYPE_TRANSACTION_STATUS = 'transaction:status_changed';
    public const TYPE_REFUND_STATUS = 'transaction_refund:status_changed';
    public const TYPE_BLIK_ALIAS_STATUS = 'blik:alias_status_changed';
    public const TYPE_BLIK_CODE_STATUS = 'transaction_blik_level0:code_status_changed';

    // BLIK alias statuses
    public const ALIAS_ACTIVE = 'alias_active';
    public const ALIAS_INACTIVE = 'alias_inactive';
    public const ALIAS_PENDING = 'alias_pending';

    /**
     * Does the status indicate a successful payment?
     */
    public static function isPaid(string $status): bool
    {
        return in_array($status, [
            self::TRANSACTION_PAID,
            self::TRANSACTION_CONFIRMED,
        ], true);
    }

    /**
     * Is this a terminal (final) status that won't change?
     */
    public static function isFinal(string $status): bool
    {
        return in_array($status, [
            self::TRANSACTION_PAID,
            self::TRANSACTION_CONFIRMED,
            self::TRANSACTION_CANCELED,
            self::TRANSACTION_EXPIRED,
            self::TRANSACTION_FAILURE,
            self::TRANSACTION_FRAUD,
            self::TRANSACTION_REFUNDED,
            self::REFUND_COMPLETED,
            self::REFUND_FAILED,
        ], true);
    }

    /**
     * Does the status indicate a failure or cancellation?
     */
    public static function isFailed(string $status): bool
    {
        return in_array($status, [
            self::TRANSACTION_CANCELED,
            self::TRANSACTION_EXPIRED,
            self::TRANSACTION_FAILURE,
            self::TRANSACTION_FRAUD,
            self::REFUND_FAILED,
        ], true);
    }
}
