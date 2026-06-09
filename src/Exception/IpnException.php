<?php

declare(strict_types=1);

namespace SimPay\SDK\Exception;

/**
 * Thrown when IPN validation fails (invalid signature, missing fields, wrong service_id, etc.)
 */
class IpnException extends SimPayException
{
}

