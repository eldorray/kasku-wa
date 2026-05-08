<?php

namespace App\Support;

use Carbon\Carbon;

class Charts
{
    public static function formatDay(string $d): string
    {
        if ($d === '2026-05-06') {
            return 'Hari ini · Rabu, 6 Mei';
        }
        if ($d === '2026-05-05') {
            return 'Kemarin · Selasa, 5 Mei';
        }

        return Carbon::parse($d)->locale('id')->isoFormat('dddd, D MMMM');
    }

    public static function arc(float $cx, float $cy, float $r, float $start, float $end): string
    {
        $sx = $cx + $r * cos(($start - 90) * M_PI / 180);
        $sy = $cy + $r * sin(($start - 90) * M_PI / 180);
        $ex = $cx + $r * cos(($end - 90) * M_PI / 180);
        $ey = $cy + $r * sin(($end - 90) * M_PI / 180);
        $large = ($end - $start) > 180 ? 1 : 0;

        return "M {$cx} {$cy} L {$sx} {$sy} A {$r} {$r} 0 {$large} 1 {$ex} {$ey} Z";
    }
}
