<?php

namespace App\Support;

/** Formatting helpers for integer minor units (paise). No floating-point arithmetic. */
final class Money
{
    public static function format(?int $minor, string $currency = 'INR'): string
    {
        if ($minor === null) {
            return 'Price on request';
        }
        $neg = $minor < 0;
        $minor = abs($minor);
        $rupees = intdiv($minor, 100);
        $paise = $minor % 100;
        $symbol = $currency === 'INR' ? '₹' : $currency.' ';

        return ($neg ? '-' : '').$symbol.self::indianGrouping($rupees).($paise ? '.'.str_pad((string) $paise, 2, '0', STR_PAD_LEFT) : '');
    }

    /** "1,00,000" style grouping. */
    public static function indianGrouping(int $n): string
    {
        $s = (string) $n;
        if (strlen($s) <= 3) {
            return $s;
        }
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);

        return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
    }

    /** Parses "1,499" / "1499.50" (rupees) into paise. Returns null for empty input. */
    public static function parseRupees(?string $input): ?int
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        $s = str_replace([',', '₹', ' '], '', trim($input));
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $s, $m)) {
            throw new \InvalidArgumentException('Enter an amount like 1499 or 1499.50');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
