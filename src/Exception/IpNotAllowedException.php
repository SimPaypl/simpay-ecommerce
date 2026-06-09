<?php

declare(strict_types=1);

namespace SimPay\SDK\Exception;

/**
 * Thrown when the requesting IP is not in SimPay's allowlist.
 */
class IpNotAllowedException extends SimPayException
{
    private string $ip;

    public function __construct(string $ip)
    {
        $this->ip = $ip;
        parent::__construct(sprintf('IP address %s is not in SimPay allowlist', $ip));
    }

    public function getIp(): string
    {
        return $this->ip;
    }
}

