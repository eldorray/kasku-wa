<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body>
        <div class="kasku-shell">
            @include('partials.kasku-sidebar')

            <div class="kasku-main">
                @include('partials.kasku-topbar', ['title' => $title ?? null])

                <div class="kasku-page kasku-page-view" wire:key="kasku-page-{{ request()->path() }}">
                    {{ $slot }}
                </div>

                <div id="kasku-drawer-host"></div>
            </div>
        </div>

        @auth
            @include('partials.kasku-mobile-chrome')
        @endauth

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
