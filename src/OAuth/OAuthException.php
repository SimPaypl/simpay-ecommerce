<?php

declare(strict_types=1);

namespace SimPay\SDK\OAuth;

use SimPay\SDK\Exception\SimPayException;

/**
 * Expected failure of the OAuth connect flow.
 *
 * Platforms should map getReason() to a translated message for the merchant;
 * getMessage() is an English description meant for logs.
 * API / transport errors are thrown as ApiException / HttpException instead.
 */
final class OAuthException extends SimPayException
{
    /** Shop URL is not https (SimPay only accepts https or localhost for plugin clients). */
    public const REDIRECT_URI_NOT_ALLOWED = 'redirect_uri_not_allowed';
    /** Merchant clicked "Deny" on the consent screen. */
    public const ACCESS_DENIED = 'access_denied';
    /** SimPay returned another error (see getDetails()). */
    public const AUTHORIZATION_FAILED = 'authorization_failed';
    /** Missing/mismatched state or no flow started in this session (CSRF, reused link, expired session). */
    public const INVALID_STATE = 'invalid_state';
    /** The account has no payment services to connect. */
    public const NO_PAYMENT_SERVICES = 'no_payment_services';
    /** The temporary access token expired before a service was selected - connect again. */
    public const EXPIRED = 'expired';
    /** The selected service is not one of the services returned for this account. */
    public const UNKNOWN_SERVICE = 'unknown_service';

    private string $reason;
    private ?string $details;

    public function __construct(string $reason, string $message, ?string $details = null)
    {
        $this->reason = $reason;
        $this->details = $details;

        parent::__construct($message);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /** Extra info, e.g. error_description returned by SimPay. */
    public function getDetails(): ?string
    {
        return $this->details;
    }
}
