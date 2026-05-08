@php
    // Mobile-only bottom tabbar + FAB.
    // CSS hides these on screens > 640px.
    $tabs = [
        ['route' => 'dashboard',  'label' => 'Beranda',   'icon' => '<path d="M3 12 L12 4 L21 12"/><path d="M5 10v10h14V10"/>'],
        ['route' => 'transaksi',  'label' => 'Transaksi', 'icon' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>'],
        ['route' => 'chat',       'label' => 'Chat WA',   'icon' => '<path d="M3 21l1.7-5.4A8 8 0 1 1 8 19l-5 2Z"/>', 'badge' => true],
        ['route' => 'laporan',    'label' => 'Laporan',   'icon' => '<line x1="3" y1="20" x2="21" y2="20"/><rect x="6" y="11" width="3" height="7"/><rect x="11" y="6" width="3" height="12"/><rect x="16" y="14" width="3" height="4"/>'],
        ['route' => 'akun',       'label' => 'Akun',      'icon' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-1Z"/><circle cx="17" cy="14" r="1.2"/>'],
    ];

    $unreadChat = 0;
    if (auth()->check()) {
        try {
            $unreadChat = (int) auth()->user()->conversations()->sum('unread');
        } catch (\Throwable $e) {
            $unreadChat = 0;
        }
    }

    $hideFabRoutes = ['chat', 'laporan'];
    $showFab = ! in_array(\Illuminate\Support\Facades\Route::currentRouteName(), $hideFabRoutes, true);
@endphp

<nav class="kasku-mobile-tabbar">
    @foreach($tabs as $t)
        <a href="{{ route($t['route']) }}" wire:navigate
           class="kasku-mobile-tab @if(request()->routeIs($t['route'])) is-active @endif">
            <div class="kasku-mobile-tab-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $t['icon'] !!}</svg>
            </div>
            <span>{{ $t['label'] }}</span>
            @if(($t['badge'] ?? false) && $unreadChat > 0)
                <span class="kasku-mobile-tab-badge">{{ $unreadChat > 9 ? '9+' : $unreadChat }}</span>
            @endif
        </a>
    @endforeach
</nav>

@if($showFab)
    <a href="{{ route('transaksi') }}?action=add" wire:navigate class="kasku-mobile-fab" aria-label="Tambah transaksi">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
    </a>
@endif
