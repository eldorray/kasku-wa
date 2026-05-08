@props(['title' => null, 'sub' => null])

<div {{ $attributes->class(['kasku-card']) }}>
    @if($title || $sub || isset($action))
        <div class="kasku-card-hd">
            <div>
                @if($title)<div class="kasku-card-title">{{ $title }}</div>@endif
                @if($sub)<div class="kasku-card-sub">{{ $sub }}</div>@endif
            </div>
            @isset($action){{ $action }}@endisset
        </div>
    @endif
    {{ $slot }}
</div>
