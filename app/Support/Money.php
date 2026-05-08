<?php

namespace App\Support;

class Money
{
    public static function fmt(int|float $n): string
    {
        $sign = $n < 0 ? '-' : '';
        $v = abs((int) $n);

        return $sign.'Rp'.number_format($v, 0, ',', '.');
    }

    public static function fmtShort(int|float $n): string
    {
        $v = abs((int) $n);
        $sign = $n < 0 ? '-' : '';

        if ($v >= 1_000_000_000) {
            return $sign.'Rp'.self::stripDot(number_format($v / 1_000_000_000, 1, '.', '')).'M';
        }
        if ($v >= 1_000_000) {
            return $sign.'Rp'.self::stripDot(number_format($v / 1_000_000, 1, '.', '')).'jt';
        }
        if ($v >= 1_000) {
            return $sign.'Rp'.((int) round($v / 1_000)).'rb';
        }

        return self::fmt($n);
    }

    private static function stripDot(string $s): string
    {
        return str_ends_with($s, '.0') ? substr($s, 0, -2) : $s;
    }
}
