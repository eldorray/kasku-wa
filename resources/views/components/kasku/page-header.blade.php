@props(['eyebrow' => null, 'title', 'sub' => null])

<div {{ $attributes->merge(['class' => 'kasku-page-hd']) }}>
    <div>
        @if($eyebrow)<div class="kasku-eyebrow" style="margin-bottom:6px">{{ $eyebrow }}</div>@endif
        <h1 class="kasku-page-title">{{ $title }}</h1>
        @if($sub)<div class="kasku-page-sub">{!! $sub !!}</div>@endif
    </div>
    @isset($actions)
        <div style="display:flex;gap:12px;align-items:center">{{ $actions }}</div>
    @endisset
</div>
