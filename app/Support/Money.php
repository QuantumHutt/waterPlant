<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PKR money helper.
 *
 * Stores/operates on rupee amounts and renders them in both exact and
 * South-Asian abbreviated form:
 *   1,000      => Rs 1K
 *   100,000    => Rs 1 Lac
 *   10,000,000 => Rs 1 Crore
 *
 * All accounting math is done in integer paisa (see {@see self::toPaisa()})
 * to avoid binary float drift — the system must catch a Rs 1 difference.
 */
final class Money
{
    public const SYMBOL = 'Rs';

    public const THOUSAND = 1_000;
    public const LAC = 100_000;
    public const CRORE = 10_000_000;

    /** Convert a rupee amount to integer paisa (safe for comparisons/sums). */
    public static function toPaisa(int|float|string $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }

    /** Convert integer paisa back to a rupee float. */
    public static function toRupees(int $paisa): float
    {
        return $paisa / 100;
    }

    /** Exact display, e.g. "Rs 1,234,567.00". */
    public static function format(int|float|string $amount, int $decimals = 2, bool $symbol = true): string
    {
        $value = number_format((float) $amount, $decimals, '.', ',');

        return $symbol ? self::SYMBOL.' '.$value : $value;
    }

    /**
     * Abbreviated display using K / Lac / Crore.
     *   999      => Rs 999
     *   1_500    => Rs 1.5K
     *   150_000  => Rs 1.5 Lac
     *   25_00_00_000 => Rs 25 Crore
     */
    public static function short(int|float|string $amount, int $decimals = 2, bool $symbol = true): string
    {
        $value = (float) $amount;
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($abs >= self::CRORE) {
            $out = self::trim($abs / self::CRORE, $decimals).' Crore';
        } elseif ($abs >= self::LAC) {
            $out = self::trim($abs / self::LAC, $decimals).' Lac';
        } elseif ($abs >= self::THOUSAND) {
            $out = self::trim($abs / self::THOUSAND, $decimals).'K';
        } else {
            $out = self::trim($abs, $decimals);
        }

        $prefix = $symbol ? self::SYMBOL.' ' : '';

        return $prefix.$sign.$out;
    }

    /** Drop trailing zeros: 1.50 => "1.5", 2.00 => "2". */
    private static function trim(float $n, int $decimals): string
    {
        $formatted = number_format($n, $decimals, '.', ',');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }
}
