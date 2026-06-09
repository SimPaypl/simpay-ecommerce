<?php

declare(strict_types=1);

namespace SimPay\SDK\Exception;

/**
 * Thrown when an HTTP transport error occurs (timeout, DNS, connection refused, etc.)
 */
class HttpException extends SimPayException
{
}

