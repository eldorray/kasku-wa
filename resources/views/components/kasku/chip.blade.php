@props(['variant' => 'default'])

@php
    $cls = 'kasku-chip';
    if ($variant !== 'default') {
        $cls .= ' kasku-chip--'.$variant;
    }
@endphp

<span {{ $attributes->class($cls) }}>{{ $slot }}</span>
