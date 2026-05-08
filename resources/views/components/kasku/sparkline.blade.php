@props(['data' => [], 'color' => '#1f8a5b', 'fill' => false])

@php
    $w = 200; $h = 36;
    $values = array_values($data);
    $max = max(max($values), 1);
    $min = min(min($values), 0);
    $range = ($max - $min) ?: 1;
    $count = count($values);
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $count > 1 ? ($i / ($count - 1)) * $w : 0;
        $y = $h - (($v - $min) / $range) * ($h - 4) - 2;
        $pts[] = $x.','.$y;
    }
    $path = 'M'.implode(' L', $pts);
    $area = $path." L{$w},{$h} L0,{$h} Z";
@endphp

<svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:100%;height:36px;display:block">
    @if($fill)<path d="{{ $area }}" fill="{{ $color }}" opacity="0.08"/>@endif
    <path d="{{ $path }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
</svg>
