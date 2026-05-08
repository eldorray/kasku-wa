@php
    $user = auth()->user();
    $logoUrl = \App\Models\AppSetting::logoUrl();
    $appName = config('app.name', 'Kasku');
    $txCount = $user?->transactions()->count() ?? 0;
    $nav = [
        ['route' => 'dashboard', 'icon' => 'home',   'label' => 'Dashboard'],
        ['route' => 'transaksi', 'icon' => 'list',   'label' => 'Transaksi', 'badge' => $txCount, 'badgeStyle' => 'ink'],
        ['route' => 'chat',      'icon' => 'chat',   'label' => 'Chat WhatsApp', 'badge' => 'live'],
        ['route' => 'kategori',  'icon' => 'tag',    'label' => 'Kategori & Budget'],
        ['route' => 'laporan',   'icon' => 'chart',  'label' => 'Laporan'],
        ['route' => 'akun',      'icon' => 'wallet', 'label' => 'Akun & Dompet'],
    ];
@endphp

<aside class="kasku-sidebar">
    <div class="kasku-brand">
        <div class="kasku-brand-mark">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" style="width:100%;height:100%;object-fit:contain;border-radius:inherit">
            @else
                k
            @endif
        </div>
        <div>
            <div class="kasku-brand-name">{{ $appName }}</div>
            <div class="kasku-brand-sub">via WhatsApp</div>
        </div>
    </div>

    <div class="kasku-nav-section">
        <div class="kasku-nav-label">Menu</div>
        @foreach($nav as $item)
            <a href="{{ route($item['route']) }}" wire:navigate class="kasku-nav-item @if(request()->routeIs($item['route'])) is-active @endif">
                <x-kasku.icon :name="$item['icon']" />
                <span>{{ $item['label'] }}</span>
                @if(isset($item['badge']))
                    <span class="kasku-nav-badge @if(($item['badgeStyle'] ?? null) === 'ink') kasku-nav-badge--ink @endif">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="kasku-nav-section">
        <div class="kasku-nav-label">Lainnya</div>
        <a href="{{ route('goals') }}" wire:navigate class="kasku-nav-item @if(request()->routeIs('goals')) is-active @endif">
            <x-kasku.icon name="target" />
            <span>Target & Goals</span>
        </a>
        <a href="{{ route('users') }}" wire:navigate class="kasku-nav-item @if(request()->routeIs('users')) is-active @endif">
            <x-kasku.icon name="user" />
            <span>User</span>
        </a>
        <a href="{{ route('profile.edit') }}" wire:navigate class="kasku-nav-item @if(request()->routeIs('profile.edit') || request()->routeIs('appearance.edit') || request()->routeIs('security.edit')) is-active @endif">
            <x-kasku.icon name="settings" />
            <span>Pengaturan</span>
        </a>
    </div>

    <div class="kasku-sidebar-footer" style="margin-top:auto">
        <div class="kasku-wa-status">
            <span class="kasku-wa-pulse"></span>
            <span>WhatsApp tersambung</span>
        </div>
        @if($user)
            <flux:dropdown position="top" align="start" class="w-full">
                <button type="button" class="kasku-user-card" style="background:transparent;border:none;width:100%;border-top:1px solid var(--color-line);padding-top:12px;cursor:pointer">
                    <div class="kasku-avatar">{{ $user->initials() }}</div>
                    <div style="min-width:0;flex:1;text-align:left">
                        <div style="font-size:13px;font-weight:500">{{ $user->name }}</div>
                        <div class="kasku-mono" style="font-size:11px;color:var(--color-ink-3)">{{ $user->email }}</div>
                    </div>
                    <x-kasku.icon name="more" :size="14" />
                </button>

                <flux:menu>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Pengaturan') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>
</aside>
