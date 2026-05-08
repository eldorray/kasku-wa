@props(['value' => 0, 'max' => 100, 'color' => 'var(--color-ink)', 'height' => '6px'])

@php
    $pct = $max > 0 ? min(100, ($value / $max) * 100) : 0;
@endphp

<div class="kasku-bar" style="height: {{ $height }}">
    <div class="kasku-bar-fill" style="width: {{ $pct }}%; background: {{ $color }}"></div>
</div>
