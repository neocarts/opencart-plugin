<?php
namespace Neocarts;

/**
 * The only place money is turned into Shop API integers and back. Nothing is converted: the shop's
 * price goes out as is, in the shop's default currency. docs/ai/money.md
 */
final class Money
{
    /** 19.99 → 1999. The inner round() kills float noise (19.99 * 100 = 1998.9999…). */
    public static function minor($amount): int
    {
        return (int) round(round((float) $amount, 4) * 100);
    }

    public static function major(int $minor): float
    {
        return $minor / 100;
    }
}
