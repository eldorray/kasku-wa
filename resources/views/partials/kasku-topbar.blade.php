@php
    $crumb = $title ?? 'Dashboard';
@endphp

<div class="kasku-topbar">
    <div class="kasku-crumb">
        <span>Kasku</span>
        <x-kasku.icon name="chevronRight" :size="12" />
        <b>{{ $crumb }}</b>
    </div>
    <div style="flex:1"></div>
    <label class="kasku-search">
        <x-kasku.icon name="search" :size="14" />
        <input placeholder="Cari transaksi, kategori, merchant…" />
        <span class="kasku-kbd">⌘K</span>
    </label>
    <button type="button" class="kasku-icon-btn"><x-kasku.icon name="bell" /></button>
    <button type="button" class="kasku-btn kasku-btn--wa"><x-kasku.icon name="wa" :size="14" /> Catat via WA</button>
</div>
