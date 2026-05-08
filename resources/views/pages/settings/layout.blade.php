<div style="display:flex;align-items:flex-start;gap:32px;flex-wrap:wrap">
    <aside style="width:220px;flex-shrink:0">
        @php
            $items = [
                ['route' => 'profile.edit',     'label' => 'Profil',     'icon' => 'settings'],
                ['route' => 'households.edit',  'label' => 'Household',  'icon' => 'settings'],
                ['route' => 'security.edit',    'label' => 'Keamanan',   'icon' => 'settings'],
                ['route' => 'appearance.edit',  'label' => 'Tampilan',   'icon' => 'eye'],
            ];
        @endphp
        <div style="display:flex;flex-direction:column;gap:2px">
            @foreach($items as $i)
                <a href="{{ route($i['route']) }}" wire:navigate
                   class="kasku-nav-item @if(request()->routeIs($i['route'])) is-active @endif"
                   style="text-decoration:none">
                    <x-kasku.icon :name="$i['icon']" />
                    <span>{{ $i['label'] }}</span>
                </a>
            @endforeach
        </div>
    </aside>

    <div style="flex:1;min-width:0">
        <div class="kasku-card">
            <div style="margin-bottom:20px">
                @if(! empty($heading))
                    <div class="kasku-card-title" style="font-size:16px">{{ $heading }}</div>
                @endif
                @if(! empty($subheading))
                    <div class="kasku-card-sub">{{ $subheading }}</div>
                @endif
            </div>
            <div style="max-width:560px">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
