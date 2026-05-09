<?php

use App\Models\Goal;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public bool $showGoalForm = false;

    public ?int $editingGoalId = null;

    #[Validate('required|string|max:255')]
    public string $goal_label = '';

    #[Validate('required|integer|min:1')]
    public int|string $goal_target = '';

    #[Validate('required|integer|min:0')]
    public int|string $goal_current = '';

    #[Validate('nullable|string|max:32')]
    public ?string $goal_due_label = null;

    #[Validate('required|string|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $goal_color = '#1f8a5b';

    public ?int $deleteGoalId = null;

    public function openCreateGoal(): void
    {
        $this->editingGoalId = null;
        $this->goal_label = '';
        $this->goal_target = '';
        $this->goal_current = '';
        $this->goal_due_label = null;
        $this->goal_color = '#1f8a5b';
        $this->resetValidation();
        $this->showGoalForm = true;
    }

    public function openEditGoal(int $id): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);

        $this->editingGoalId = $goal->id;
        $this->goal_label = $goal->label;
        $this->goal_target = (int) $goal->target;
        $this->goal_current = (int) $goal->current;
        $this->goal_due_label = $goal->due_label;
        $this->goal_color = $goal->color;
        $this->resetValidation();
        $this->showGoalForm = true;
    }

    public function closeGoalForm(): void
    {
        $this->showGoalForm = false;
        $this->editingGoalId = null;
        $this->resetValidation();
    }

    public function saveGoal(): void
    {
        $this->validate();

        $payload = [
            'label' => $this->goal_label,
            'target' => (int) $this->goal_target,
            'current' => min((int) $this->goal_current, (int) $this->goal_target),
            'due_label' => $this->goal_due_label ?: null,
            'color' => $this->goal_color,
        ];

        $payload['user_id'] = Auth::user()->id;
        if ($this->editingGoalId) {
            currentHousehold()->goals()->findOrFail($this->editingGoalId)->update($payload);
            Flux::toast(variant: 'success', text: 'Goal diperbarui.');
        } else {
            currentHousehold()->goals()->create($payload);
            Flux::toast(variant: 'success', text: 'Goal baru ditambahkan.');
        }

        $this->closeGoalForm();
    }

    public function addGoalProgress(int $id, int $amount): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);
        $goal->update([
            'current' => min((int) $goal->target, (int) $goal->current + $amount),
        ]);

        Flux::toast(variant: 'success', text: 'Progress goal ditambahkan.');
    }

    public function confirmDeleteGoal(int $id): void
    {
        $this->deleteGoalId = currentHousehold()->goals()->findOrFail($id)->id;
        Flux::modal('delete-goal')->show();
    }

    public function deleteGoal(): void
    {
        if (! $this->deleteGoalId) {
            return;
        }

        currentHousehold()->goals()->findOrFail($this->deleteGoalId)->delete();
        $this->deleteGoalId = null;
        Flux::modal('delete-goal')->close();
        Flux::toast(variant: 'success', text: 'Goal dihapus.');
    }

    public function with(): array
    {
        $user = Auth::user();
        $household = currentHousehold();
        $accounts = $household->accounts()->get();
        $totalBalance = (int) $accounts->sum('balance');
        $recent = $household->transactions()
            ->with(['category', 'account'])
            ->orderByDesc('occurred_at')
            ->limit(6)
            ->get();
        $now = Carbon::now();
        $period = $now->format('Y-m');
        $monthLabel = $now->locale('id')->isoFormat('MMMM YYYY');
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $budgets = $household->budgets()
            ->with('category')
            ->where('period', $period)
            ->get()
            ->map(function ($b) use ($household) {
                $start = Carbon::parse($b->period.'-01')->startOfMonth();
                $end = (clone $start)->endOfMonth();
                $spent = (int) abs((int) $household->transactions()
                    ->where('category_id', $b->category_id)
                    ->whereBetween('occurred_at', [$start, $end])
                    ->where('type', Transaction::TYPE_EXPENSE)
                    ->sum('amount'));

                return (object) [
                    'category' => $b->category,
                    'limit' => (int) $b->monthly_limit,
                    'spent' => $spent,
                ];
            });
        $goals = $household->goals()
            ->orderByRaw('CASE WHEN target > 0 THEN current / target ELSE 0 END DESC')
            ->orderBy('id')
            ->get();

        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $cursor = $now->copy()->subMonthsNoOverflow($i);
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();
            $income = (int) $household->transactions()
                ->whereBetween('occurred_at', [$start, $end])
                ->where('type', Transaction::TYPE_INCOME)
                ->sum('amount');
            $expense = (int) abs((int) $household->transactions()
                ->whereBetween('occurred_at', [$start, $end])
                ->where('type', Transaction::TYPE_EXPENSE)
                ->sum('amount'));
            $monthly[] = [
                'm' => $cursor->locale('id')->isoFormat('MMM'),
                'income' => $income,
                'expense' => $expense,
            ];
        }

        $current = $monthly[5];
        $previousIncome = (int) $household->transactions()
            ->whereBetween('occurred_at', [$previousMonthStart, $previousMonthEnd])
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');
        $previousExpense = (int) abs((int) $household->transactions()
            ->whereBetween('occurred_at', [$previousMonthStart, $previousMonthEnd])
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount'));
        $incomeChange = $this->percentageChange($previousIncome, $current['income']);
        $expenseChange = $this->percentageChange($previousExpense, $current['expense']);

        $dailyIncome = [];
        $dailyExpense = [];
        for ($i = 9; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dailyIncome[] = max(1, (int) $household->transactions()
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->where('type', Transaction::TYPE_INCOME)
                ->sum('amount'));
            $dailyExpense[] = max(1, (int) abs((int) $household->transactions()
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->where('type', Transaction::TYPE_EXPENSE)
                ->sum('amount')));
        }

        $startWeek = $now->copy()->subDays(6)->startOfDay();
        $endWeek = $now->copy()->endOfDay();
        $waCount = (int) $household->transactions()
            ->where('via', 'wa')
            ->whereBetween('occurred_at', [$startWeek, $endWeek])
            ->count();
        $totalWeek = (int) $household->transactions()
            ->whereBetween('occurred_at', [$startWeek, $endWeek])
            ->count();

        return compact('accounts', 'totalBalance', 'recent', 'budgets', 'goals', 'monthly', 'current', 'waCount', 'totalWeek', 'monthLabel', 'incomeChange', 'expenseChange', 'dailyIncome', 'dailyExpense');
    }

    private function percentageChange(int $previous, int $current): int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round(($current - $previous) / abs($previous) * 100);
    }
};
?>

<div>
    {{-- Mobile appbar --}}
    <div class="kasku-mobile-appbar">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f7d488,#c47a14);display:grid;place-items:center;color:white;font-weight:600;font-size:13px">
            {{ Auth::user()->initials() }}
        </div>
        <div style="flex:1">
            <div style="font-size:11px;color:var(--color-ink-3)">Selamat datang</div>
            <div style="font-weight:500;font-size:14px">{{ Auth::user()->name }}</div>
        </div>
        <div class="kasku-mobile-appbar-actions">
            <a href="{{ route('households.edit') }}" wire:navigate class="kasku-mobile-appbar-icon" aria-label="Pengaturan">
                <x-kasku.icon name="settings" :size="16" />
            </a>
        </div>
    </div>

    {{-- Mobile hero balance card --}}
    <div class="kasku-mobile-only" style="padding:0 0 8px">
        <div class="kasku-mobile-balance">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;position:relative;z-index:1">
                <span style="font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:var(--color-on-invert-3);font-weight:500">Total Saldo · {{ $accounts->count() }} akun</span>
            </div>
            <div class="kasku-mobile-display" style="font-size:34px;position:relative;z-index:1">{{ \App\Support\Money::fmt($totalBalance) }}</div>
            <div style="font-size:11px;color:var(--color-on-invert-3);margin-top:6px;position:relative;z-index:1">{{ $monthLabel }} · saving rate <b style="color:var(--color-on-invert)">{{ $current['income'] > 0 ? (int) round(($current['income'] - $current['expense']) / $current['income'] * 100) : 0 }}%</b></div>
            <div class="kasku-mobile-keep-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;position:relative;z-index:1">
                <div style="background:var(--color-on-invert-bg);border-radius:12px;padding:10px 12px">
                    <div style="font-size:11px;color:var(--color-on-invert-3)">Masuk</div>
                    <div style="font-weight:500;margin-top:2px;font-variant-numeric:tabular-nums">{{ \App\Support\Money::fmtShort($current['income']) }}</div>
                </div>
                <div style="background:var(--color-on-invert-bg);border-radius:12px;padding:10px 12px">
                    <div style="font-size:11px;color:var(--color-on-invert-3)">Keluar</div>
                    <div style="font-weight:500;margin-top:2px;font-variant-numeric:tabular-nums">{{ \App\Support\Money::fmtShort($current['expense']) }}</div>
                </div>
            </div>
        </div>

        {{-- WA banner mobile --}}
        <a href="{{ route('chat') }}" wire:navigate class="kasku-wa-banner" style="border-radius:18px;padding:14px 16px;margin-top:14px;display:flex;align-items:center;gap:12px;text-decoration:none">
            <div style="width:38px;height:38px;background:var(--color-wa);border-radius:50%;display:grid;place-items:center;color:white;flex-shrink:0">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.7-5.4A8 8 0 1 1 8 19l-5 2Z"/></svg>
            </div>
            <div style="flex:1">
                <div class="kasku-wa-banner-title" style="font-weight:500;font-size:13px">Catat transaksi via chat</div>
                <div class="kasku-wa-banner-sub" style="font-size:11px;margin-top:2px">"kopi 25rb" · "/laporan" · &lt;2 detik balasan</div>
            </div>
        </a>
    </div>

    <x-kasku.page-header
        class="kasku-desktop-only"
        :eyebrow="'Selamat datang, ' . Auth::user()->name . ' 👋'"
        title="Ringkasan Keuangan"
        :sub="$monthLabel . ' — ringkasan keuangan Anda bulan ini.'">
        <x-slot:actions>
            <button type="button" class="kasku-btn"><x-kasku.icon name="calendar" :size="14" /> {{ $monthLabel }}</button>
            <button type="button" class="kasku-btn"><x-kasku.icon name="download" :size="14" /> Ekspor</button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid kasku-grid-4 kasku-desktop-only" style="margin-bottom:20px">
        <div class="kasku-card kasku-card--invert">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <span class="kasku-eyebrow">Total Saldo</span>
                <button type="button" class="kasku-icon-btn kasku-invert-icon-btn"><x-kasku.icon name="eye" :size="14" /></button>
            </div>
            <div class="kasku-display" style="font-size:36px">{{ \App\Support\Money::fmt($totalBalance) }}</div>
            <div class="kasku-mono kasku-on-invert-3" style="font-size:11px;margin-top:8px">{{ $accounts->count() }} akun · Bank, e-wallet, tunai</div>
        </div>

        <div class="kasku-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <span class="kasku-eyebrow">Pemasukan {{ $monthLabel }}</span>
                <x-kasku.chip :variant="$incomeChange >= 0 ? 'pos' : 'neg'"><x-kasku.icon :name="$incomeChange >= 0 ? 'trendUp' : 'trendDown'" :size="11" /> {{ $incomeChange >= 0 ? '+' : '' }}{{ $incomeChange }}%</x-kasku.chip>
            </div>
            <div class="kasku-display" style="font-size:30px;color:var(--color-pos)">{{ \App\Support\Money::fmt($current['income']) }}</div>
            <x-kasku.sparkline :data="$dailyIncome" color="#1f8a5b" :fill="true" />
        </div>

        <div class="kasku-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <span class="kasku-eyebrow">Pengeluaran {{ $monthLabel }}</span>
                <x-kasku.chip :variant="$expenseChange <= 0 ? 'pos' : 'neg'"><x-kasku.icon :name="$expenseChange <= 0 ? 'trendDown' : 'trendUp'" :size="11" /> {{ $expenseChange >= 0 ? '+' : '' }}{{ $expenseChange }}%</x-kasku.chip>
            </div>
            <div class="kasku-display" style="font-size:30px;color:var(--color-neg)">{{ \App\Support\Money::fmt($current['expense']) }}</div>
            <x-kasku.sparkline :data="$dailyExpense" color="#c0382b" :fill="true" />
        </div>

        <div class="kasku-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <span class="kasku-eyebrow">Saving Rate</span>
                <x-kasku.chip variant="pos">target 60%</x-kasku.chip>
            </div>
            @php
                $rate = $current['income'] > 0 ? (int) round(($current['income'] - $current['expense']) / $current['income'] * 100) : 0;
            @endphp
            <div class="kasku-display" style="font-size:30px">{{ $rate }}%</div>
            <div style="margin-top:14px"><x-kasku.bar :value="$rate" :max="100" color="var(--color-pos)" /></div>
        </div>
    </div>

    <div class="kasku-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
        <x-kasku.card title="Cashflow 6 bulan" sub="Pemasukan vs pengeluaran bulanan">
            <x-slot:action>
                <div class="kasku-tabs">
                    <button type="button" class="kasku-tab">3M</button>
                    <button type="button" class="kasku-tab is-active">6M</button>
                    <button type="button" class="kasku-tab">1Y</button>
                </div>
            </x-slot:action>
            <x-kasku.cashflow-chart :monthly="$monthly" />
        </x-kasku.card>

        <div class="kasku-card" style="background:var(--color-wa-bg);border-color:transparent">
            <div class="kasku-card-hd">
                <div>
                    <div class="kasku-card-title" style="color:var(--color-wa-ink);display:flex;align-items:center;gap:8px"><x-kasku.icon name="wa" :size="14" /> Aktivitas WhatsApp</div>
                    <div class="kasku-card-sub" style="color:var(--color-wa-deep)">{{ $totalWeek }} transaksi via chat minggu ini</div>
                </div>
            </div>
            <div class="kasku-display" style="font-size:56px;color:var(--color-wa-ink);line-height:0.9">{{ $waCount }}<span style="font-size:22px;opacity:0.6">/{{ $totalWeek ?: 20 }}</span></div>
            <div style="font-size:11px;color:var(--color-wa-deep);margin-top:6px;margin-bottom:16px">tx via chat &nbsp;·&nbsp; {{ $totalWeek > 0 ? (int) round($waCount / $totalWeek * 100) : 0 }}% otomasi</div>
            <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--color-wa-ink)">
                <div style="display:flex;align-items:center;gap:12px"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">💬</div>Chat natural</div>
                <div style="display:flex;align-items:center;gap:12px"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">📷</div>Foto struk</div>
                <div style="display:flex;align-items:center;gap:12px"><div style="width:24px;height:24px;background:white;border-radius:6px;display:grid;place-items:center">⚡</div>Command /expense</div>
            </div>
            <a href="{{ route('chat') }}" wire:navigate class="kasku-btn kasku-btn--wa" style="margin-top:18px;width:100%;justify-content:center;text-decoration:none">Buka chat <x-kasku.icon name="arrowRight" :size="12" /></a>
        </div>
    </div>

    <div class="kasku-grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);margin-top:20px">
        <div class="kasku-card" style="padding:0">
            <div class="kasku-card-hd" style="padding:20px;margin-bottom:0">
                <div>
                    <div class="kasku-card-title">Transaksi terbaru</div>
                    <div class="kasku-card-sub">{{ $recent->count() }} transaksi terakhir</div>
                </div>
                <a href="{{ route('transaksi') }}" wire:navigate class="kasku-btn kasku-btn--ghost">Lihat semua <x-kasku.icon name="arrowRight" :size="12" /></a>
            </div>
            <table class="kasku-tbl">
                <tbody>
                @foreach($recent as $t)
                    <tr>
                        <td style="width:44px"><x-kasku.cat-icon :category="$t->category" /></td>
                        <td>
                            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px">{{ $t->label }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px">{{ $t->category->label }} · {{ $t->account->label }}</div>
                        </td>
                        <td class="kasku-desktop-only" style="width:100px"><x-kasku.via-chip :via="$t->via" /></td>
                        <td style="text-align:right;font-weight:500;white-space:nowrap" class="kasku-money @if($t->amount < 0) kasku-money--neg @else kasku-money--pos @endif">{{ \App\Support\Money::fmtShort($t->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <x-kasku.card title="Budget bulan ini" :sub="$budgets->count() . ' kategori dipantau'">
            <div style="display:flex;flex-direction:column;gap:14px">
                @foreach($budgets as $b)
                    @php
                        $pct = $b->limit > 0 ? min(100, (int) round($b->spent / $b->limit * 100)) : 0;
                        $over = $b->spent > $b->limit;
                        $color = $over ? 'var(--color-neg)' : ($pct > 80 ? 'var(--color-warn)' : 'var(--color-ink)');
                    @endphp
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-size:14px">{{ $b->category->emoji }}</span>
                                <span style="font-size:13px">{{ $b->category->label }}</span>
                                @if($over)<x-kasku.chip variant="neg" style="padding:1px 6px;font-size:10px">over</x-kasku.chip>@endif
                            </div>
                            <div class="kasku-tabular" style="font-size:11px;color:var(--color-ink-3)">{{ \App\Support\Money::fmtShort($b->spent) }} / {{ \App\Support\Money::fmtShort($b->limit) }}</div>
                        </div>
                        <x-kasku.bar :value="$b->spent" :max="$b->limit" :color="$color" />
                    </div>
                @endforeach
            </div>
        </x-kasku.card>
    </div>

    <div style="margin-top:32px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div>
                <div class="kasku-card-title">Goals & target tabungan</div>
                <div class="kasku-card-sub">{{ $goals->count() }} target aktif</div>
            </div>
            <button type="button" wire:click="openCreateGoal" class="kasku-btn kasku-btn--ghost"><x-kasku.icon name="plus" :size="12" /> Goal baru</button>
        </div>
        <div class="kasku-grid kasku-grid-3">
            @forelse($goals as $g)
                @php
                    $pct = $g->target > 0 ? min(100, (int) round($g->current / $g->target * 100)) : 0;
                    $remaining = max(0, (int) $g->target - (int) $g->current);
                @endphp
                <div class="kasku-card" wire:key="goal-{{ $g->id }}">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div class="kasku-eyebrow">{{ $g->due_label ?: 'Tanpa deadline' }}</div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <x-kasku.chip>{{ $pct }}%</x-kasku.chip>
                            <flux:dropdown align="end">
                                <button type="button" class="kasku-icon-btn" style="border:none">
                                    <x-kasku.icon name="more" />
                                </button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditGoal({{ $g->id }})">Edit goal</flux:menu.item>
                                    <flux:menu.item icon="plus" wire:click="addGoalProgress({{ $g->id }}, 100000)">Tambah Rp100rb</flux:menu.item>
                                    <flux:menu.item icon="plus" wire:click="addGoalProgress({{ $g->id }}, 500000)">Tambah Rp500rb</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteGoal({{ $g->id }})">Hapus goal</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                    <div class="kasku-display" style="font-size:22px;margin-top:12px;margin-bottom:4px">{{ $g->label }}</div>
                    <div style="font-size:11px;color:var(--color-ink-3);margin-bottom:8px">
                        <span class="kasku-tabular" style="color:var(--color-ink);font-weight:500">{{ \App\Support\Money::fmt($g->current) }}</span>
                        dari {{ \App\Support\Money::fmt($g->target) }}
                    </div>
                    <div style="font-size:11px;color:var(--color-ink-3);margin-bottom:16px">
                        Sisa target: <span class="kasku-tabular" style="color:var(--color-ink);font-weight:500">{{ \App\Support\Money::fmt($remaining) }}</span>
                    </div>
                    <x-kasku.bar :value="$g->current" :max="$g->target" :color="$g->color" height="4px" />
                </div>
            @empty
                <button type="button" wire:click="openCreateGoal" class="kasku-card" style="border-style:dashed;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--color-ink-3);min-height:180px;cursor:pointer;background:transparent;font-family:inherit;text-align:center">
                    <div style="width:44px;height:44px;border-radius:11px;border:1.5px dashed var(--color-line-2);display:grid;place-items:center;margin-bottom:12px"><x-kasku.icon name="plus" :size="20" /></div>
                    <div style="font-weight:500;color:var(--color-ink-2)">Buat target tabungan pertama</div>
                    <div style="font-size:11px;margin-top:4px">Contoh: Dana darurat, liburan, gadget</div>
                </button>
            @endforelse
        </div>
    </div>

    @if($showGoalForm)
        <div class="kasku-overlay" wire:click="closeGoalForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="saveGoal">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingGoalId ? 'Edit Goal' : 'Goal Baru' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeGoalForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    <div style="display:flex;gap:14px;align-items:center;padding:14px;background:var(--color-bg-sunken);border-radius:10px">
                        <div style="width:44px;height:44px;border-radius:11px;background:{{ $goal_color }};color:white;display:grid;place-items:center;font-weight:600;font-size:14px">
                            🎯
                        </div>
                        <div>
                            <div class="kasku-eyebrow">Pratinjau target</div>
                            <div style="font-weight:500;font-size:14px;margin-top:2px">{{ $goal_label ?: 'Nama goal' }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">
                                {{ \App\Support\Money::fmt((int) ($goal_current ?: 0)) }} / {{ \App\Support\Money::fmt((int) ($goal_target ?: 0)) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Nama goal</label>
                        <input type="text" wire:model.live.debounce.300ms="goal_label" placeholder="Mis. Dana darurat, Liburan, Laptop baru" class="kasku-form-input" />
                        @error('goal_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Target nominal (Rp)</label>
                            <input type="number" min="1" step="1000" wire:model="goal_target" placeholder="10000000" class="kasku-form-input" />
                            @error('goal_target')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Terkumpul saat ini (Rp)</label>
                            <input type="number" min="0" step="1000" wire:model="goal_current" placeholder="2500000" class="kasku-form-input" />
                            @error('goal_current')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Deadline / catatan waktu <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                        <input type="text" wire:model.live.debounce.300ms="goal_due_label" placeholder="Mis. Des 2026" class="kasku-form-input" />
                        @error('goal_due_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Warna goal</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" wire:model.live="goal_color" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--color-line);cursor:pointer;padding:2px" />
                            <input type="text" wire:model.live.debounce.300ms="goal_color" placeholder="#1f8a5b" class="kasku-form-input kasku-mono" style="flex:1" />
                        </div>
                        @error('goal_color')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="button" wire:click="closeGoalForm" class="kasku-btn" style="flex:1;justify-content:center">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-btn kasku-btn--primary" style="flex:1;justify-content:center">
                            <span wire:loading.remove wire:target="saveGoal">{{ $editingGoalId ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="saveGoal">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <flux:modal name="delete-goal" class="md:w-[420px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus goal</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">Yakin hapus goal ini?</div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    Target tabungan akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">Batal</button>
                </flux:modal.close>
                <button type="button" wire:click="deleteGoal" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                    <span wire:loading.remove wire:target="deleteGoal">Ya, hapus</span>
                    <span wire:loading wire:target="deleteGoal">Menghapus…</span>
                </button>
            </div>
        </div>
    </flux:modal>
</div>
