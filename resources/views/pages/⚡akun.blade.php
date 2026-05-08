<?php

use App\Models\Transaction;
use App\Services\AccountService;
use App\Services\TransactionService;
use App\Support\Money;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Akun & Dompet')] class extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $form_label = '';

    #[Validate('required|in:Bank,E-wallet,Cash,Kartu Kredit,Investasi,Lainnya')]
    public string $form_type = 'Bank';

    #[Validate('nullable|string|max:8')]
    public ?string $form_last_four = null;

    #[Validate('required|integer|min:0')]
    public int|string $form_balance = '';

    #[Validate('required|string|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $form_color = '#1d4ed8';

    public ?int $deleteId = null;
    public ?int $deleteTxCount = null;

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form_label = '';
        $this->form_type = 'Bank';
        $this->form_last_four = null;
        $this->form_balance = '';
        $this->form_color = '#1d4ed8';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $a = currentHousehold()->accounts()->findOrFail($id);
        $this->editingId = $a->id;
        $this->form_label = $a->label;
        $this->form_type = $a->type;
        $this->form_last_four = $a->last_four;
        $this->form_balance = (int) $a->balance;
        $this->form_color = $a->color;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(AccountService $service, TransactionService $txService): void
    {
        $this->validate();

        try {
            if ($this->editingId) {
                $a = currentHousehold()->accounts()->findOrFail($this->editingId);
                $service->update($a, Auth::user(), [
                    'label' => $this->form_label,
                    'type' => $this->form_type,
                    'last_four' => $this->form_last_four,
                    'color' => $this->form_color,
                ]);

                $target = (int) $this->form_balance;
                if ($target !== (int) $a->balance) {
                    $service->adjustBalance($a->fresh(), Auth::user(), $target, $txService);
                }

                Flux::toast(variant: 'success', text: 'Akun diperbarui.');
            } else {
                $service->create(currentHousehold(), Auth::user(), [
                    'label' => $this->form_label,
                    'type' => $this->form_type,
                    'last_four' => $this->form_last_four,
                    'balance' => (int) $this->form_balance,
                    'color' => $this->form_color,
                ]);
                Flux::toast(variant: 'success', text: 'Akun ditambahkan.');
            }
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }

        $this->closeForm();
    }

    public function confirmDelete(int $id): void
    {
        $a = currentHousehold()->accounts()->findOrFail($id);
        $this->deleteId = $a->id;
        $this->deleteTxCount = (int) $a->transactions()->count();
        Flux::modal('delete-account')->show();
    }

    public function delete(AccountService $service): void
    {
        if (! $this->deleteId) {
            return;
        }
        $a = currentHousehold()->accounts()->findOrFail($this->deleteId);
        try {
            $result = $service->delete($a, Auth::user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }

        if (! $result['ok']) {
            $msg = match ($result['error']) {
                'has_transactions' => 'Tidak bisa hapus: masih ada '.($result['tx_count'] ?? 0).' transaksi pakai akun ini.',
                'has_goals' => 'Tidak bisa hapus: ada '.($result['goal_count'] ?? 0).' goal yang terhubung dengan akun ini.',
                default => 'Tidak bisa hapus akun.',
            };
            Flux::toast(variant: 'danger', text: $msg);
        } else {
            Flux::toast(variant: 'success', text: 'Akun dihapus.');
        }

        $this->deleteId = null;
        $this->deleteTxCount = null;
        Flux::modal('delete-account')->close();
    }

    public function with(AccountService $service): array
    {
        $household = currentHousehold();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $accounts = $household->accounts()->with('user')->orderBy('id')->get()->map(function ($a) use ($monthStart, $monthEnd) {
            // Exclude transfers from inflow/outflow — transfers are not real income/expense.
            $txs = $a->transactions()
                ->where('type', '!=', Transaction::TYPE_TRANSFER)
                ->whereBetween('occurred_at', [$monthStart, $monthEnd])
                ->get();

            return (object) [
                'account' => $a,
                'tx_count' => $txs->count(),
                'inflow' => (int) $txs->where('amount', '>', 0)->sum('amount'),
                'outflow' => (int) $txs->where('amount', '<', 0)->sum('amount'),
            ];
        });
        $totalBalance = (int) $accounts->sum(fn ($r) => $r->account->balance);

        $series = $service->historicalTotalBalance($household, 30);

        return compact('accounts', 'totalBalance', 'series');
    }
};
?>

<div>
    {{-- Mobile appbar --}}
    <div class="kasku-mobile-appbar">
        <div style="flex:1">
            <div class="kasku-mobile-appbar-title">Akun & Dompet</div>
            <div class="kasku-mobile-appbar-sub">{{ $accounts->count() }} sumber · {{ \App\Support\Money::fmt($totalBalance) }}</div>
        </div>
        <div class="kasku-mobile-appbar-actions">
            <button type="button" wire:click="openCreate" class="kasku-mobile-appbar-icon" aria-label="Tambah akun">
                <x-kasku.icon name="plus" :size="16" />
            </button>
        </div>
    </div>

    {{-- Mobile balance summary --}}
    <div class="kasku-mobile-only" style="padding:0 0 8px">
        <div class="kasku-mobile-balance">
            <div style="font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.5);font-weight:500;position:relative;z-index:1">Total saldo gabungan</div>
            <div class="kasku-mobile-display" style="font-size:32px;margin-top:6px;position:relative;z-index:1">{{ \App\Support\Money::fmt($totalBalance) }}</div>
        </div>
    </div>

    <x-kasku.page-header
        class="kasku-desktop-only"
        eyebrow="Akun & Dompet"
        title="Semua Saldo"
        :sub="$accounts->count() . ' sumber dana · Total saldo ' . \App\Support\Money::fmt($totalBalance)">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="kasku-btn kasku-btn--primary">
                <x-kasku.icon name="plus" :size="14" /> Tambah akun
            </button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid kasku-grid-2" style="margin-bottom:32px">
        @foreach($accounts as $row)
            @php $a = $row->account; @endphp
            <div class="kasku-card" wire:key="acc-{{ $a->id }}" style="position:relative;overflow:hidden">
                <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:{{ $a->color }};opacity:0.06"></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;position:relative">
                    <div style="display:flex;gap:12px;align-items:center">
                        <div style="width:44px;height:44px;border-radius:11px;background:{{ $a->color }};color:white;display:grid;place-items:center;font-weight:600;font-size:14px">{{ mb_strtoupper(mb_substr($a->label, 0, 2)) }}</div>
                        <div>
                            <div style="font-weight:500">{{ $a->label }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3)">{{ $a->type }}@if($a->last_four) ·••{{ $a->last_four }}@endif</div>
                            <div style="font-size:11px;color:var(--color-wa-deep);margin-top:3px">Dibuat oleh: {{ $a->user?->name ?? 'User dihapus' }}</div>
                        </div>
                    </div>

                    <flux:dropdown align="end">
                        <button type="button" class="kasku-icon-btn" style="border:none">
                            <x-kasku.icon name="more" />
                        </button>
                        <flux:menu>
                            @if(currentHousehold()->canEdit(auth()->user()))
                                <flux:menu.item icon="pencil" wire:click="openEdit({{ $a->id }})">Edit akun</flux:menu.item>
                            @endif
                            <flux:menu.item icon="rectangle-stack" :href="route('transaksi', ['account' => $a->id])" wire:navigate>Lihat transaksi</flux:menu.item>
                            @if(currentHousehold()->canEdit(auth()->user()))
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $a->id }})">Hapus akun</flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </div>
                <div class="kasku-eyebrow">Saldo</div>
                <div class="kasku-display" style="font-size:32px;margin-top:6px">{{ \App\Support\Money::fmt($a->balance) }}</div>
                <div style="display:flex;gap:16px;margin-top:18px;padding-top:16px;border-top:1px solid var(--color-line)">
                    <div style="flex:1">
                        <div style="font-size:11px;color:var(--color-ink-3)">Masuk bulan ini</div>
                        <div class="kasku-tabular kasku-money kasku-money--pos" style="font-weight:500;margin-top:2px">{{ \App\Support\Money::fmtShort($row->inflow) }}</div>
                    </div>
                    <div style="flex:1">
                        <div style="font-size:11px;color:var(--color-ink-3)">Keluar bulan ini</div>
                        <div class="kasku-tabular kasku-money kasku-money--neg" style="font-weight:500;margin-top:2px">{{ \App\Support\Money::fmtShort($row->outflow) }}</div>
                    </div>
                    <div style="flex:1">
                        <div style="font-size:11px;color:var(--color-ink-3)">Transaksi</div>
                        <div class="kasku-tabular" style="font-weight:500;margin-top:2px">{{ $row->tx_count }}</div>
                    </div>
                </div>
            </div>
        @endforeach

        <button type="button" wire:click="openCreate" class="kasku-card" style="border-style:dashed;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--color-ink-3);min-height:220px;cursor:pointer;background:transparent;font-family:inherit;text-align:center">
            <div style="width:44px;height:44px;border-radius:11px;border:1.5px dashed var(--color-line-2);display:grid;place-items:center;margin-bottom:12px"><x-kasku.icon name="plus" :size="20" /></div>
            <div style="font-weight:500;color:var(--color-ink-2)">Hubungkan akun baru</div>
            <div style="font-size:11px;margin-top:4px">Bank · E-wallet · Kartu kredit</div>
        </button>
    </div>

    {{-- Real historical balance chart --}}
    <x-kasku.card title="Tren saldo total" sub="Pergerakan saldo gabungan {{ count($series) }} hari terakhir">
        @php
            $values = array_map(fn ($r) => $r['balance'], $series);
            $maxV = max($values);
            $minV = min($values);
            $range = ($maxV - $minV) ?: max($maxV, 1);
            $count = count($values);
            $w = 800;
            $h = 220;
            $padX = 10;
            $padY = 20;
            $stepX = $count > 1 ? ($w - 2 * $padX) / ($count - 1) : 0;
            $pathParts = [];
            $points = [];
            foreach ($values as $i => $v) {
                $x = $padX + $i * $stepX;
                $y = ($range > 0)
                    ? $h - $padY - (($v - $minV) / $range) * ($h - 2 * $padY)
                    : $h / 2;
                $pathParts[] = ($i === 0 ? 'M' : 'L')." {$x} {$y}";
                $points[] = compact('x', 'y');
            }
            $path = implode(' ', $pathParts);
            $area = $path.' L '.($padX + ($count - 1) * $stepX).' '.($h - $padY).' L '.$padX.' '.($h - $padY).' Z';
            $firstDate = $series[0]['date'] ?? null;
            $lastDate = $series[count($series) - 1]['date'] ?? null;
            $delta = $values[$count - 1] - $values[0];
        @endphp

        <div style="display:flex;align-items:baseline;gap:16px;margin-bottom:16px">
            <div class="kasku-display" style="font-size:30px">{{ \App\Support\Money::fmt($values[$count - 1] ?? 0) }}</div>
            <div class="kasku-tabular" style="font-size:13px;color:{{ $delta >= 0 ? 'var(--color-pos)' : 'var(--color-neg)' }}">
                {{ $delta >= 0 ? '+' : '' }}{{ \App\Support\Money::fmt($delta) }} <span style="color:var(--color-ink-3);font-weight:normal">vs 30 hari lalu</span>
            </div>
        </div>

        <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:220px;display:block">
            {{-- gridlines --}}
            @foreach([0, 0.25, 0.5, 0.75, 1] as $g)
                <line x1="{{ $padX }}" x2="{{ $w - $padX }}" y1="{{ $padY + $g * ($h - 2 * $padY) }}" y2="{{ $padY + $g * ($h - 2 * $padY) }}" stroke="var(--color-line)" stroke-dasharray="2 4"/>
            @endforeach
            <path d="{{ $area }}" fill="var(--color-ink)" opacity="0.05"/>
            <path d="{{ $path }}" fill="none" stroke="var(--color-ink)" stroke-width="2"/>
            @foreach($points as $i => $p)
                @if($i % 5 === 0 || $i === $count - 1)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="var(--color-bg-elev)" stroke="var(--color-ink)" stroke-width="2"/>
                @endif
            @endforeach
        </svg>

        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-ink-3);margin-top:8px">
            <span>{{ $firstDate ? \Carbon\Carbon::parse($firstDate)->locale('id')->isoFormat('D MMM') : '' }}</span>
            <span>{{ $lastDate ? \Carbon\Carbon::parse($lastDate)->locale('id')->isoFormat('D MMM') : '' }}</span>
        </div>
    </x-kasku.card>

    {{-- Create / Edit drawer --}}
    @if($showForm)
        <div class="kasku-overlay" wire:click="closeForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="save">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingId ? 'Edit Akun' : 'Tambah Akun' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    {{-- Preview --}}
                    <div style="display:flex;gap:14px;align-items:center;padding:14px;background:var(--color-bg-sunken);border-radius:10px">
                        <div style="width:44px;height:44px;border-radius:11px;background:{{ $form_color }};color:white;display:grid;place-items:center;font-weight:600;font-size:14px">
                            {{ $form_label ? mb_strtoupper(mb_substr($form_label, 0, 2)) : '??' }}
                        </div>
                        <div>
                            <div class="kasku-eyebrow">Pratinjau</div>
                            <div style="font-weight:500;font-size:14px;margin-top:2px">{{ $form_label ?: 'Nama akun' }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $form_type }}@if($form_last_four) ·••{{ $form_last_four }}@endif</div>
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Nama akun</label>
                        <input type="text" wire:model.live.debounce.300ms="form_label" placeholder="Mis. BCA Tahapan, GoPay" class="kasku-form-input" />
                        @error('form_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Tipe</label>
                            <select wire:model.live="form_type" class="kasku-form-input">
                                <option value="Bank">Bank</option>
                                <option value="E-wallet">E-wallet</option>
                                <option value="Cash">Cash / Tunai</option>
                                <option value="Kartu Kredit">Kartu Kredit</option>
                                <option value="Investasi">Investasi</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                            @error('form_type')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">4 digit terakhir <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                            <input type="text" wire:model.live="form_last_four" maxlength="8" placeholder="4521" class="kasku-form-input kasku-mono" />
                            @error('form_last_four')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Saldo {{ $editingId ? 'saat ini' : 'awal' }} (Rp)</label>
                        <input type="number" min="0" step="1000" wire:model="form_balance" placeholder="500000" class="kasku-form-input" />
                        @if($editingId)
                            <div style="font-size:11px;color:var(--color-warn);margin-top:6px;line-height:1.5">⚠️ Mengubah saldo manual tidak membuat transaksi otomatis. Gunakan Transaksi → Tambah manual jika perlu mencatat penyesuaian.</div>
                        @endif
                        @error('form_balance')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Warna identitas</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" wire:model.live="form_color" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--color-line);cursor:pointer;padding:2px" />
                            <input type="text" wire:model.live.debounce.300ms="form_color" placeholder="#1d4ed8" class="kasku-form-input kasku-mono" style="flex:1" />
                        </div>
                        @error('form_color')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="button" wire:click="closeForm" class="kasku-btn" style="flex:1;justify-content:center">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-btn kasku-btn--primary" style="flex:1;justify-content:center">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Delete modal --}}
    @php $hasBlockingTx = ($deleteTxCount ?? 0) > 0; @endphp
    <flux:modal name="delete-account" class="md:w-[420px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus akun</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">
                    {{ $hasBlockingTx ? 'Tidak bisa dihapus' : 'Yakin hapus akun ini?' }}
                </div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    @if($hasBlockingTx)
                        Akun ini masih dipakai <b style="color:var(--color-neg)">{{ $deleteTxCount }} transaksi</b>. Pindahkan/hapus transaksi tersebut dulu.
                    @else
                        Tindakan ini tidak dapat dibatalkan.
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">{{ $hasBlockingTx ? 'Tutup' : 'Batal' }}</button>
                </flux:modal.close>
                @if(! $hasBlockingTx)
                    <button type="button" wire:click="delete" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                        <span wire:loading.remove wire:target="delete">Ya, hapus</span>
                        <span wire:loading wire:target="delete">Menghapus…</span>
                    </button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Mobile-only shortcuts: Kategori, Goals, Users, Pengaturan --}}
    <div class="kasku-mobile-only" style="padding:0 0 24px;margin-top:18px">
        <div class="kasku-mobile-section"><h3>Lainnya</h3></div>
        <div class="kasku-card" style="padding:0">
            @php
                $shortcuts = [
                    ['route' => 'kategori', 'icon' => '🏷️', 'label' => 'Kategori & Budget'],
                    ['route' => 'goals',    'icon' => '🎯', 'label' => 'Target & Goals'],
                    ['route' => 'users',    'icon' => '👥', 'label' => 'User'],
                    ['route' => 'households.edit', 'icon' => '🏠', 'label' => 'Household'],
                    ['route' => 'profile.edit',    'icon' => '⚙️', 'label' => 'Pengaturan'],
                ];
            @endphp
            @foreach($shortcuts as $i => $s)
                <a href="{{ route($s['route']) }}" wire:navigate
                   style="width:100%;display:flex;align-items:center;gap:12px;padding:14px;text-align:left;background:transparent;text-decoration:none;color:inherit;@if(! $loop->last)border-bottom:1px solid var(--color-line);@endif">
                    <span style="font-size:18px">{{ $s['icon'] }}</span>
                    <div style="flex:1">
                        <div style="font-weight:500;font-size:13px">{{ $s['label'] }}</div>
                    </div>
                    <span style="color:var(--color-ink-3)">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div style="text-align:center;font-size:11px;color:var(--color-ink-3);margin-top:24px">
            Kasku · {{ config('app.name', 'Kasku') }}
        </div>
    </div>
</div>
