@props(['via'])

@switch($via)
    @case('wa')
        <span class="kasku-chip kasku-chip--wa"><x-kasku.icon name="wa" :size="11" />chat</span>
        @break
    @case('receipt')
        <span class="kasku-chip kasku-chip--wa"><x-kasku.icon name="camera" :size="11" />struk</span>
        @break
    @default
        <span class="kasku-chip">manual</span>
@endswitch
