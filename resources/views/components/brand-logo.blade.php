@props([
    'class' => 'size-9',
    'iconClass' => 'size-9 fill-current text-black dark:text-white',
    'alt' => null,
])

@php
    $logoUrl = \App\Models\AppSetting::logoUrl();
    $appName = config('app.name', 'Kasku');
@endphp

@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ $alt ?? $appName.' logo' }}" {{ $attributes->merge(['class' => $class.' object-contain']) }}>
@else
    <x-app-logo-icon class="{{ $iconClass }}" />
@endif