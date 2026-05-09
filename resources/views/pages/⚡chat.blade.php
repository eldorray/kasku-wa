<?php

use App\Models\Message;
use App\Services\Whatsapp\WhatsappBot;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chat WhatsApp')] class extends Component
{
    public string $draft = '';

    public function send(): void
    {
        $body = trim($this->draft);
        if ($body === '') {
            return;
        }
        $this->draft = '';

        $user = Auth::user();
        if (! $user->phone) {
            Flux::toast(variant: 'warning', text: 'Isi nomor WhatsApp di Pengaturan dulu agar bot bisa identifikasi pengirim.');
            return;
        }

        app(WhatsappBot::class)->handleIncoming($user->phone, $body, Carbon::now());
    }

    public function quickFill(string $text): void
    {
        $this->draft = $text;
    }

    public function clearChat(): void
    {
        $user = Auth::user();
        $botSlug = config('whatsapp.bot.conversation_slug', 'kasku');
        $conv = $user->conversations()->where('slug', $botSlug)->first();
        if ($conv) {
            Message::where('user_id', $user->id)
                ->where('conversation_id', $conv->id)
                ->delete();
            $conv->update(['last_message' => null, 'last_at_label' => null, 'unread' => 0]);
        }
        Flux::toast(variant: 'success', text: 'Riwayat chat dibersihkan.');
    }

    public function with(): array
    {
        $user = Auth::user();
        $botSlug = config('whatsapp.bot.conversation_slug', 'kasku');
        $botConv = $user->conversations()->where('slug', $botSlug)->first();

        $messages = Message::query()
            ->where('user_id', $user->id)
            ->when($botConv, fn ($q) => $q->where('conversation_id', $botConv->id))
            ->orderBy('occurred_at')
            ->limit(60)
            ->get();

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $waCount = (int) $user->transactions()
            ->where('via', 'wa')
            ->whereBetween('occurred_at', [$weekStart, $weekEnd])
            ->count();
        $totalWeek = (int) $user->transactions()
            ->whereBetween('occurred_at', [$weekStart, $weekEnd])
            ->count();

        $sendReal = (bool) config('whatsapp.send_real');
        $hasToken = (bool) config('whatsapp.fonnte.token');
        $connectionState = match (true) {
            $sendReal && $hasToken => ['label' => 'Tersambung', 'variant' => 'pos', 'note' => 'Fonnte aktif (mengirim WA real)'],
            $hasToken => ['label' => 'Token siap', 'variant' => 'warn', 'note' => 'WA_SEND_REAL=false, balasan hanya di-log'],
            default => ['label' => 'Mode Demo', 'variant' => 'warn', 'note' => 'Belum konfigurasi FONNTE_TOKEN'],
        };

        $deviceNumber = config('whatsapp.fonnte.device_number');
        $waLink = $deviceNumber ? 'https://wa.me/'.$deviceNumber : null;

        return compact('messages', 'waCount', 'totalWeek', 'connectionState', 'user', 'waLink');
    }
};
?>

<div>
    {{-- Mobile WA-style header --}}
    <div class="kasku-mobile-only">
        <div style="background:#075E54;color:white;padding:12px 14px;display:flex;align-items:center;gap:12px">
            <a href="{{ route('dashboard') }}" wire:navigate style="color:white;opacity:0.85" aria-label="Kembali">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            </a>
            <div style="width:38px;height:38px;background:white;border-radius:50%;color:#075E54;display:grid;place-items:center;font-family:var(--font-display,'Instrument Serif',serif);font-size:20px">k</div>
            <div style="flex:1">
                <div style="font-weight:500;font-size:15px">Kasku Bot</div>
                <div style="font-size:11px;opacity:0.85">online · &lt;2 detik balasan</div>
            </div>
            @if($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" style="color:white;opacity:0.9" aria-label="Buka WhatsApp">
                    <x-kasku.icon name="wa" :size="18" />
                </a>
            @endif
        </div>
    </div>

    <x-kasku.page-header
        class="kasku-desktop-only"
        eyebrow="Chat WhatsApp"
        title="Bot Kasku"
        sub="Catat transaksi, minta laporan, atau cek saldo — semuanya lewat chat.">
        <x-slot:actions>
            <a href="{{ route('profile.edit') }}" wire:navigate class="kasku-btn" style="text-decoration:none">
                <x-kasku.icon name="settings" /> Pengaturan bot
            </a>
            @if($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="kasku-btn kasku-btn--wa" style="text-decoration:none">
                    <x-kasku.icon name="wa" :size="14" /> Buka di WhatsApp
                </a>
            @else
                <button type="button" class="kasku-btn kasku-btn--wa" disabled title="Set FONNTE_DEVICE_NUMBER di .env" style="opacity:0.5;cursor:not-allowed">
                    <x-kasku.icon name="wa" :size="14" /> Buka di WhatsApp
                </button>
            @endif
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid" style="grid-template-columns:minmax(0,1fr) 360px;gap:24px;align-items:start;height:calc(100vh - 240px);min-height:600px">
        {{-- Phone preview --}}
        <div style="display:flex;align-items:flex-start;justify-content:center;padding-top:8px" wire:poll.5s>
            <div class="kasku-phone-frame">
                <div class="kasku-phone-screen">
                    <div class="kasku-wa-header">
                        <div class="kasku-wa-bot-avatar">k</div>
                        <div style="flex:1">
                            <div style="font-size:15px;font-weight:500;line-height:1.1">{{ config('whatsapp.bot.name', 'Kasku Bot') }}</div>
                            <div style="font-size:11px;opacity:0.85;line-height:1.3">online · membalas dalam &lt;2 detik</div>
                        </div>
                        <button type="button"
                                wire:click="clearChat"
                                wire:confirm="Hapus seluruh riwayat chat dengan bot?"
                                title="Bersihkan riwayat chat"
                                style="background:transparent;border:none;color:white;opacity:0.7;cursor:pointer;padding:4px">
                            <x-kasku.icon name="x" :size="18" />
                        </button>
                    </div>
                    <div class="kasku-wa-bg" x-data="{}" x-init="$el.scrollTop = $el.scrollHeight" x-effect="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                        @forelse($messages as $m)
                            @php $outgoing = $m->direction === 'in'; @endphp
                            <div class="kasku-wa-bubble {{ $outgoing ? 'kasku-wa-bubble--out' : 'kasku-wa-bubble--in' }}">
                                <div style="white-space:pre-wrap">{{ $m->body }}</div>
                                <div class="kasku-wa-time">
                                    {{ $m->occurred_at->format('H:i') }}
                                    @if($outgoing)<span class="read">✓✓</span>@endif
                                </div>
                            </div>
                        @empty
                            <div class="kasku-wa-bubble kasku-wa-bubble--in">
                                <div>Halo {{ $user->name }} 👋 Aku Kasku Bot.<br>Ketik transaksi seperti <b>kopi 28rb</b> atau ketik <b>/help</b> untuk lihat perintah.</div>
                                <div class="kasku-wa-time">{{ now()->format('H:i') }}</div>
                            </div>
                        @endforelse
                    </div>
                    <form wire:submit="send" class="kasku-wa-input" wire:key="kasku-chat-form">
                        <span style="color:#888"><x-kasku.icon name="camera" :size="20" /></span>
                        <input
                            type="text"
                            wire:model="draft"
                            placeholder="Ketik pesan… coba 'kopi 28rb' atau '/help'"
                            class="kasku-wa-input-box"
                            style="border:none;outline:none;font-family:inherit;color:#222"
                            autocomplete="off"
                            autofocus>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-wa-input-send" style="border:none;cursor:pointer">
                            <x-kasku.icon name="send" :size="16" />
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right panel --}}
        <div style="display:flex;flex-direction:column;gap:16px">
            <x-kasku.card title="Status koneksi">
                <x-slot:action>
                    <span class="kasku-chip kasku-chip--{{ $connectionState['variant'] }}">
                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor"></span> {{ $connectionState['label'] }}
                    </span>
                </x-slot:action>
                <div style="display:flex;gap:12px;align-items:center;padding:12px;background:var(--color-wa-bg);border-radius:10px">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--color-wa);display:grid;place-items:center;color:white"><x-kasku.icon name="wa" :size="18" /></div>
                    <div style="min-width:0;flex:1">
                        <div style="font-weight:500;font-size:13px">{{ $user->phone ?: 'Nomor belum diisi' }}</div>
                        <div style="font-size:11px;color:var(--color-wa-deep)">{{ $connectionState['note'] }}</div>
                    </div>
                </div>
                @if(! $user->phone)
                    <a href="{{ route('profile.edit') }}" wire:navigate class="kasku-btn" style="margin-top:12px;width:100%;justify-content:center;text-decoration:none">
                        Isi nomor di Pengaturan
                    </a>
                @endif
            </x-kasku.card>

            <x-kasku.card title="Aksi cepat" sub="Klik untuk isi otomatis ke chat">
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach([
                        ['ico' => '💰', 't' => 'Catat pengeluaran',  'fill' => 'kopi 25rb'],
                        ['ico' => '🛵', 't' => 'Catat transport',     'fill' => 'gojek 30rb'],
                        ['ico' => '📊', 't' => 'Minta laporan bulan', 'fill' => '/laporan'],
                        ['ico' => '🎯', 't' => 'Cek status budget',   'fill' => '/budget'],
                        ['ico' => '💳', 't' => 'Cek saldo akun',      'fill' => '/saldo'],
                        ['ico' => '❓', 't' => 'Lihat bantuan',        'fill' => '/help'],
                    ] as $a)
                        <button type="button"
                                wire:click="quickFill('{{ addslashes($a['fill']) }}')"
                                style="display:flex;gap:12px;padding:10px;border-radius:8px;align-items:center;cursor:pointer;background:transparent;border:1px solid transparent;text-align:left;width:100%;font-family:inherit;transition:background 120ms,border-color 120ms"
                                onmouseover="this.style.background='var(--color-bg-sunken)';this.style.borderColor='var(--color-line)'"
                                onmouseout="this.style.background='transparent';this.style.borderColor='transparent'">
                            <div style="font-size:18px;flex-shrink:0">{{ $a['ico'] }}</div>
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:500;font-size:13px;color:var(--color-ink)">{{ $a['t'] }}</div>
                                <div class="kasku-mono" style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $a['fill'] }}</div>
                            </div>
                            <x-kasku.icon name="arrowRight" :size="12" />
                        </button>
                    @endforeach
                </div>
            </x-kasku.card>

            <div class="kasku-card kasku-card--invert">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                    <div class="kasku-eyebrow">Minggu ini</div>
                    <span style="opacity:0.5"><x-kasku.icon name="sparkle" :size="14" /></span>
                </div>
                <div class="kasku-display" style="font-size:36px">{{ $waCount }}<span class="kasku-on-invert-3" style="font-size:18px">/{{ max($totalWeek, $waCount) }}</span></div>
                <div class="kasku-on-invert-3" style="font-size:11px;margin-top:4px">Transaksi via chat</div>
                <div class="kasku-bar" style="margin-top:14px;background:var(--color-on-invert-bg)">
                    <div class="kasku-bar-fill" style="width:{{ $totalWeek > 0 ? min(100, (int) round($waCount / $totalWeek * 100)) : 0 }}%;background:var(--color-wa)"></div>
                </div>
            </div>
        </div>
    </div>
</div>
