@props([
    'sidebar' => false,
])

@php
    $logoUrl = \App\Models\AppSetting::logoUrl();
    $appName = config('app.name', 'Kasku');
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ $appName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-accent-content text-accent-foreground">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" class="h-full w-full object-contain">
            @else
                <x-app-logo-icon class="size-5 fill-current text-accent-foreground" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $appName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-accent-content text-accent-foreground">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" class="h-full w-full object-contain">
            @else
                <x-app-logo-icon class="size-5 fill-current text-accent-foreground" />
            @endif
        </x-slot>
    </flux:brand>
@endif