<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * Float-safe amount comparison.
 *
 * Consolidates the amount verification logic previously duplicated across platforms:
 * - OpenCart: floatLessThan($a, $b, $epsilon)
 * - PrestaShop: isLessThan($a, $b, $precision)
 */
final class AmountVerifier
{
    /**
     * Check if the paid amount is sufficient (>= expected order amount).
     *
     * Usage:
     *   if (!AmountVerifier::isAmountSufficient($orderTotal, $paidAmount)) {
     *       // amount too low
     *   }
     */
    public static function isAmountSufficient(float $orderAmount, float $paidAmount): bool
    {
        // Compare in minor units (int) to avoid float precision issues
        $orderMinor = (int) round($orderAmount * 100);
        $paidMinor = (int) round($paidAmount * 100);

        return $paidMinor >= $orderMinor;
    }
}
