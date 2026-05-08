<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BudgetService;
use App\Services\CategoryService;
use App\Support\Money;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Kategori & Budget')] class extends Component
{
    public string $period = '';

    // Category form state
    public bool $showCatForm = false;
    public ?int $editingCatId = null;

    #[Validate('required|string|max:255')]
    public string $cat_label = '';

    #[Validate('required|string|max:8')]
    public string $cat_emoji = '✨';

    #[Validate('required|in:both,income,expense')]
    public string $cat_type = 'expense';

    #[Validate('required|string|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $cat_color = '#6b7280';

    #[Validate('required|string|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $cat_bg = '#f3f4f6';

    public ?int $deleteCatId = null;
    public ?int $deleteCatTxCount = null;

    // Budget form state
    public bool $showBudgetForm = false;
    public ?int $editingBudgetId = null;
    public ?int $budget_category_id = null;

    #[Validate('required|integer|min:1')]
    public int|string $budget_limit = '';

    public ?int $deleteBudgetId = null;

    public function mount(): void
    {
        $this->period = Carbon::now()->format('Y-m');
    }

    /* ----------------------- Category CRUD ----------------------- */

    public function openCatCreate(): void
    {
        $this->editingCatId = null;
        $this->cat_label = '';
        $this->cat_emoji = '✨';
        $this->cat_type = 'expense';
        $this->cat_color = '#6b7280';
        $this->cat_bg = '#f3f4f6';
        $this->resetValidation();
        $this->showCatForm = true;
    }

    public function openCatEdit(int $id): void
    {
        $cat = Category::findOrFail($id);
        $this->editingCatId = $cat->id;
        $this->cat_label = $cat->label;
        $this->cat_emoji = $cat->emoji;
        $this->cat_type = $cat->type ?? 'expense';
        $this->cat_color = $cat->color;
        $this->cat_bg = $cat->bg;
        $this->resetValidation();
        $this->showCatForm = true;
    }

    public function closeCatForm(): void
    {
        $this->showCatForm = false;
        $this->editingCatId = null;
        $this->resetValidation();
    }

    public function saveCat(CategoryService $service): void
    {
        $this->validateOnly('cat_label');
        $this->validateOnly('cat_emoji');
        $this->validateOnly('cat_color');
        $this->validateOnly('cat_bg');

        $payload = [
            'label' => $this->cat_label,
            'emoji' => $this->cat_emoji,
            'type' => $this->cat_type,
            'color' => $this->cat_color,
            'bg' => $this->cat_bg,
        ];

        if ($this->editingCatId) {
            $service->update(Category::findOrFail($this->editingCatId), $payload);
            Flux::toast(variant: 'success', text: 'Kategori diperbarui.');
        } else {
            $service->create($payload);
            Flux::toast(variant: 'success', text: 'Kategori ditambahkan.');
        }

        $this->closeCatForm();
    }

    public function confirmDeleteCat(int $id): void
    {
        $cat = Category::findOrFail($id);
        $this->deleteCatId = $cat->id;
        $this->deleteCatTxCount = (int) $cat->transactions()->count();
        Flux::modal('delete-cat')->show();
    }

    public function deleteCat(CategoryService $service): void
    {
        if (! $this->deleteCatId) {
            return;
        }
        $cat = Category::findOrFail($this->deleteCatId);
        $result = $service->delete($cat);

        if (! $result['ok']) {
            Flux::toast(variant: 'danger', text: 'Tidak bisa hapus: ada '.$result['tx_count'].' transaksi memakai kategori ini.');
        } else {
            Flux::toast(variant: 'success', text: 'Kategori dihapus.');
        }

        $this->deleteCatId = null;
        $this->deleteCatTxCount = null;
        Flux::modal('delete-cat')->close();
    }

    /* ----------------------- Budget CRUD ----------------------- */

    public function openBudgetCreate(?int $categoryId = null): void
    {
        $this->editingBudgetId = null;
        $this->budget_category_id = $categoryId;
        $this->budget_limit = '';
        $this->resetValidation();
        $this->showBudgetForm = true;
    }

    public function openBudgetEdit(int $id): void
    {
        $b = currentHousehold()->budgets()->findOrFail($id);
        $this->editingBudgetId = $b->id;
        $this->budget_category_id = $b->category_id;
        $this->budget_limit = (int) $b->monthly_limit;
        $this->resetValidation();
        $this->showBudgetForm = true;
    }

    public function closeBudgetForm(): void
    {
        $this->showBudgetForm = false;
        $this->editingBudgetId = null;
        $this->budget_category_id = null;
        $this->resetValidation();
    }

    public function saveBudget(BudgetService $service): void
    {
        $this->validate([
            'budget_category_id' => 'required|exists:categories,id',
            'budget_limit' => 'required|integer|min:1',
        ]);

        try {
            $service->setLimit(
                currentHousehold(),
                Auth::user(),
                (int) $this->budget_category_id,
                $this->period,
                (int) $this->budget_limit,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }

        Flux::toast(variant: 'success', text: $this->editingBudgetId ? 'Budget diperbarui.' : 'Budget ditambahkan.');
        $this->closeBudgetForm();
    }

    public function confirmDeleteBudget(int $id): void
    {
        $this->deleteBudgetId = $id;
        Flux::modal('delete-budget')->show();
    }

    public function deleteBudget(BudgetService $service): void
    {
        if (! $this->deleteBudgetId) {
            return;
        }
        $b = currentHousehold()->budgets()->findOrFail($this->deleteBudgetId);
        try { $service->delete($b, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        $this->deleteBudgetId = null;
        Flux::modal('delete-budget')->close();
        Flux::toast(variant: 'success', text: 'Budget dihapus.');
    }

    public function clonePreviousMonth(BudgetService $service): void
    {
        try { $result = $service->cloneFromPreviousMonth(currentHousehold(), Auth::user(), $this->period); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        if ($result['copied'] === 0) {
            Flux::toast(variant: 'warning', text: 'Tidak ada budget bulan lalu yang bisa disalin'.($result['skipped'] > 0 ? ' (semua sudah ada di bulan ini)' : '').'.');
        } else {
            Flux::toast(variant: 'success', text: $result['copied'].' budget disalin dari bulan lalu.'.($result['skipped'] > 0 ? ' '.$result['skipped'].' di-skip karena sudah ada.' : ''));
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $household = currentHousehold();
        $period = $this->period;
        $start = Carbon::parse($period.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $budgets = $household->budgets()
            ->with('category')
            ->where('period', $period)
            ->orderBy('id')
            ->get()
            ->map(function ($b) use ($household, $start, $end) {
                $spent = abs((int) $household->transactions()
                    ->where('category_id', $b->category_id)
                    ->where('type', Transaction::TYPE_EXPENSE)
                    ->whereBetween('occurred_at', [$start, $end])
                    ->sum('amount'));
                $txCount = (int) $household->transactions()
                    ->where('category_id', $b->category_id)
                    ->where('type', Transaction::TYPE_EXPENSE)
                    ->whereBetween('occurred_at', [$start, $end])
                    ->count();

                return (object) [
                    'budget_id' => $b->id,
                    'category' => $b->category,
                    'limit' => (int) $b->monthly_limit,
                    'spent' => $spent,
                    'tx_count' => $txCount,
                ];
            });

        $categories = Category::orderBy('label')->get()->map(function ($c) use ($household) {
            $txCount = (int) $household->transactions()
                ->where('category_id', $c->id)
                ->count();
            $totalNeg = (int) $household->transactions()
                ->where('category_id', $c->id)
                ->where('type', Transaction::TYPE_EXPENSE)
                ->sum('amount');

            return (object) [
                'category' => $c,
                'tx_count' => $txCount,
                'total' => $totalNeg,
            ];
        });

        $budgetedCategoryIds = $budgets->pluck('category.id')->all();
        $categoriesWithoutBudget = Category::whereNotIn('id', $budgetedCategoryIds)->orderBy('label')->get();

        $previousPeriod = Carbon::parse($period.'-01')->subMonth()->format('Y-m');
        $hasPreviousBudgets = $household->budgets()->where('period', $previousPeriod)->exists();

        return compact('budgets', 'categories', 'categoriesWithoutBudget', 'hasPreviousBudgets');
    }
};
?>

<div>
    <x-kasku.page-header
        eyebrow="Kategori & Budget"
        title="Kelola Pengeluaran"
        sub="Atur batas bulanan tiap kategori, dapatkan notifikasi di WhatsApp saat mendekati limit.">
        <x-slot:actions>
            @if($hasPreviousBudgets)
                <button type="button" wire:click="clonePreviousMonth" class="kasku-btn">
                    <x-kasku.icon name="download" :size="14" /> Salin bulan lalu
                </button>
            @endif
            <button type="button" wire:click="openBudgetCreate" class="kasku-btn">
                <x-kasku.icon name="plus" :size="14" /> Tambah budget
            </button>
            <button type="button" wire:click="openCatCreate" class="kasku-btn kasku-btn--primary">
                <x-kasku.icon name="plus" :size="14" /> Kategori baru
            </button>
        </x-slot:actions>
    </x-kasku.page-header>

    @php
        $totalLimit = (int) $budgets->sum('limit');
        $totalSpent = (int) $budgets->sum('spent');
        $pctTotal = $totalLimit > 0 ? (int) round($totalSpent / $totalLimit * 100) : 0;
        $overCount = $budgets->filter(fn ($b) => $b->spent > $b->limit)->count();
        $warnCount = $budgets->filter(fn ($b) => $b->limit > 0 && $b->spent / $b->limit > 0.8 && $b->spent <= $b->limit)->count();
        $needAttention = $overCount + $warnCount;
    @endphp

    <div class="kasku-grid kasku-grid-3" style="margin-bottom:24px">
        <div class="kasku-card">
            <div class="kasku-eyebrow">Total budget bulanan</div>
            <div class="kasku-display" style="font-size:30px;margin-top:8px">{{ \App\Support\Money::fmt($totalLimit) }}</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">{{ $budgets->count() }} kategori dipantau</div>
        </div>
        <div class="kasku-card">
            <div class="kasku-eyebrow">Terpakai bulan ini</div>
            <div class="kasku-display" style="font-size:30px;margin-top:8px">{{ \App\Support\Money::fmt($totalSpent) }}</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">{{ $pctTotal }}% dari budget</div>
        </div>
        <div class="kasku-card" style="background:rgba(196,122,20,0.08);border-color:transparent">
            <div class="kasku-eyebrow" style="color:var(--color-warn)">Perlu perhatian</div>
            <div class="kasku-display" style="font-size:30px;margin-top:8px;color:var(--color-warn)">{{ $needAttention }} kategori</div>
            <div style="font-size:11px;color:var(--color-warn);margin-top:8px">
                @if($overCount > 0)
                    {{ $overCount }} over-budget
                @endif
                @if($overCount > 0 && $warnCount > 0)
                    ·
                @endif
                @if($warnCount > 0)
                    {{ $warnCount }} hampir habis
                @endif
                @if($needAttention === 0)
                    Semua aman 🎉
                @endif
            </div>
        </div>
    </div>

    {{-- Budgets --}}
    @if($budgets->isEmpty())
        <div class="kasku-card" style="text-align:center;padding:48px 20px">
            <div style="font-size:36px;margin-bottom:12px">🎯</div>
            <div style="font-weight:500;font-size:16px">Belum ada budget</div>
            <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;margin-bottom:20px">Mulai dengan menambahkan budget untuk kategori yang sering Anda gunakan.</div>
            <button type="button" wire:click="openBudgetCreate" class="kasku-btn kasku-btn--primary"><x-kasku.icon name="plus" :size="14" /> Tambah budget pertama</button>
        </div>
    @else
        <div class="kasku-grid kasku-grid-2">
            @foreach($budgets as $b)
                @php
                    $c = $b->category;
                    $pct = $b->limit > 0 ? (int) round($b->spent / $b->limit * 100) : 0;
                    $over = $b->spent > $b->limit;
                    $remain = $b->limit - $b->spent;
                    $barColor = $over ? 'var(--color-neg)' : ($pct > 80 ? 'var(--color-warn)' : $c->color);
                @endphp
                <div class="kasku-card" wire:key="budget-{{ $b->budget_id }}">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                        <div style="display:flex;gap:12px;align-items:center">
                            <div class="kasku-cat-icon" style="width:40px;height:40px;background:{{ $c->bg }};color:{{ $c->color }};font-size:18px">{{ $c->emoji }}</div>
                            <div>
                                <div style="font-weight:500">{{ $c->label }}</div>
                                <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $b->tx_count }} transaksi bulan ini</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center">
                            @if($over)
                                <x-kasku.chip variant="neg">Over budget</x-kasku.chip>
                            @elseif($pct > 80)
                                <x-kasku.chip variant="warn">Hampir habis</x-kasku.chip>
                            @else
                                <x-kasku.chip variant="pos">Aman</x-kasku.chip>
                            @endif
                            <button type="button" wire:click="openBudgetEdit({{ $b->budget_id }})" class="kasku-icon-btn" title="Edit budget" style="width:30px;height:30px"><x-kasku.icon name="settings" :size="13" /></button>
                            <button type="button" wire:click="confirmDeleteBudget({{ $b->budget_id }})" class="kasku-icon-btn" title="Hapus budget" style="width:30px;height:30px;color:var(--color-neg)"><x-kasku.icon name="x" :size="13" /></button>
                        </div>
                    </div>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:8px">
                        <div class="kasku-display kasku-tabular" style="font-size:24px">{{ \App\Support\Money::fmt($b->spent) }}</div>
                        <div style="color:var(--color-ink-3);font-size:12px">/ {{ \App\Support\Money::fmt($b->limit) }}</div>
                    </div>
                    <x-kasku.bar :value="$b->spent" :max="$b->limit" :color="$barColor" height="8px" />
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;font-size:12px">
                        <span style="color:var(--color-ink-3)">{{ $pct }}% terpakai</span>
                        <span class="kasku-tabular @if($over) kasku-money kasku-money--neg @else kasku-muted @endif" style="font-weight:500">
                            {{ $over ? 'Over '.\App\Support\Money::fmt(abs($remain)) : 'Sisa '.\App\Support\Money::fmt($remain) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- All categories --}}
    <div style="margin-top:32px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div>
                <div class="kasku-card-title">Semua kategori</div>
                <div class="kasku-card-sub">{{ $categories->count() }} kategori · klik untuk edit</div>
            </div>
            <button type="button" wire:click="openCatCreate" class="kasku-btn kasku-btn--ghost"><x-kasku.icon name="plus" :size="12" /> Tambah</button>
        </div>
        <div class="kasku-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
            @foreach($categories as $row)
                @php $cat = $row->category; @endphp
                <div class="kasku-card" wire:key="cat-{{ $cat->id }}" style="padding:16px;position:relative;cursor:pointer" wire:click="openCatEdit({{ $cat->id }})">
                    <button type="button"
                            wire:click.stop="confirmDeleteCat({{ $cat->id }})"
                            class="kasku-icon-btn"
                            title="Hapus"
                            style="position:absolute;top:8px;right:8px;width:24px;height:24px;border:none;background:transparent;color:var(--color-ink-3);opacity:0.6">
                        <x-kasku.icon name="x" :size="12" />
                    </button>
                    <div class="kasku-cat-icon" style="background:{{ $cat->bg }};color:{{ $cat->color }};font-size:18px;margin-bottom:12px">{{ $cat->emoji }}</div>
                    <div style="font-weight:500;font-size:13px">{{ $cat->label }}</div>
                    <div style="font-size:11px;color:var(--color-ink-3);margin-top:4px">{{ $row->tx_count }} tx · {{ \App\Support\Money::fmtShort($row->total) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Category form drawer --}}
    @if($showCatForm)
        <div class="kasku-overlay" wire:click="closeCatForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="saveCat">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingCatId ? 'Edit Kategori' : 'Kategori Baru' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeCatForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    {{-- Preview --}}
                    <div style="display:flex;gap:12px;align-items:center;padding:14px;background:var(--color-bg-sunken);border-radius:10px">
                        <div class="kasku-cat-icon" style="width:48px;height:48px;background:{{ $cat_bg }};color:{{ $cat_color }};font-size:22px">{{ $cat_emoji ?: '✨' }}</div>
                        <div>
                            <div class="kasku-eyebrow">Pratinjau</div>
                            <div style="font-weight:500;font-size:14px;margin-top:2px">{{ $cat_label ?: 'Nama kategori' }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Label</label>
                        <input type="text" wire:model.live.debounce.300ms="cat_label" placeholder="Mis. Olahraga, Donasi" class="kasku-form-input" />
                        @error('cat_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Emoji</label>
                        <input type="text" wire:model.live.debounce.300ms="cat_emoji" maxlength="4" placeholder="🎯" class="kasku-form-input" style="font-size:18px" />
                        @error('cat_emoji')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Tipe transaksi</label>
                        <select wire:model="cat_type" class="kasku-form-input">
                            <option value="expense">Pengeluaran</option>
                            <option value="income">Pemasukan</option>
                            <option value="both">Keduanya</option>
                        </select>
                        @error('cat_type')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Warna ikon</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" wire:model.live="cat_color" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--color-line);cursor:pointer;padding:2px" />
                                <input type="text" wire:model.live.debounce.300ms="cat_color" placeholder="#6b7280" class="kasku-form-input kasku-mono" style="flex:1" />
                            </div>
                            @error('cat_color')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Warna latar</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" wire:model.live="cat_bg" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--color-line);cursor:pointer;padding:2px" />
                                <input type="text" wire:model.live.debounce.300ms="cat_bg" placeholder="#f3f4f6" class="kasku-form-input kasku-mono" style="flex:1" />
                            </div>
                            @error('cat_bg')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="button" wire:click="closeCatForm" class="kasku-btn" style="flex:1;justify-content:center">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-btn kasku-btn--primary" style="flex:1;justify-content:center">
                            <span wire:loading.remove wire:target="saveCat">{{ $editingCatId ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="saveCat">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Budget form drawer --}}
    @if($showBudgetForm)
        <div class="kasku-overlay" wire:click="closeBudgetForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="saveBudget">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingBudgetId ? 'Edit Budget' : 'Tambah Budget' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeBudgetForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Periode</label>
                        <input type="text" value="{{ \Carbon\Carbon::parse($period.'-01')->locale('id')->isoFormat('MMMM YYYY') }}" disabled class="kasku-form-input" style="opacity:0.7" />
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Kategori</label>
                        @if($editingBudgetId)
                            @php $cat = \App\Models\Category::find($budget_category_id); @endphp
                            <div style="display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--color-line);border-radius:10px;background:var(--color-bg-sunken)">
                                <div class="kasku-cat-icon" style="background:{{ $cat->bg }};color:{{ $cat->color }}">{{ $cat->emoji }}</div>
                                <div style="font-size:13px;font-weight:500">{{ $cat->label }}</div>
                            </div>
                        @else
                            <select wire:model="budget_category_id" class="kasku-form-input">
                                <option value="">— Pilih kategori —</option>
                                @foreach($categoriesWithoutBudget as $c)
                                    <option value="{{ $c->id }}">{{ $c->emoji }} {{ $c->label }}</option>
                                @endforeach
                            </select>
                            @if($categoriesWithoutBudget->isEmpty())
                                <div class="kasku-form-error" style="color:var(--color-warn)">Semua kategori sudah punya budget bulan ini.</div>
                            @endif
                            @error('budget_category_id')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        @endif
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Limit bulanan (Rp)</label>
                        <input type="number" min="1" step="1000" wire:model="budget_limit" placeholder="500000" class="kasku-form-input" />
                        @error('budget_limit')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="button" wire:click="closeBudgetForm" class="kasku-btn" style="flex:1;justify-content:center">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-btn kasku-btn--primary" style="flex:1;justify-content:center">
                            <span wire:loading.remove wire:target="saveBudget">{{ $editingBudgetId ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="saveBudget">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Delete category modal --}}
    @php
        $hasBlockingTx = ($deleteCatTxCount ?? 0) > 0;
    @endphp
    <flux:modal name="delete-cat" class="md:w-[420px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus kategori</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">
                    {{ $hasBlockingTx ? 'Tidak bisa dihapus' : 'Yakin hapus kategori ini?' }}
                </div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    @if($hasBlockingTx)
                        Kategori ini masih dipakai <b style="color:var(--color-neg)">{{ $deleteCatTxCount }} transaksi</b>. Pindahkan/hapus transaksi tersebut dulu, lalu coba lagi.
                    @else
                        Budget yang terkait dengan kategori ini juga akan ikut terhapus. Tindakan tidak dapat dibatalkan.
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">{{ $hasBlockingTx ? 'Tutup' : 'Batal' }}</button>
                </flux:modal.close>
                @if(! $hasBlockingTx)
                    <button type="button" wire:click="deleteCat" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                        <span wire:loading.remove wire:target="deleteCat">Ya, hapus</span>
                        <span wire:loading wire:target="deleteCat">Menghapus…</span>
                    </button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Delete budget modal --}}
    <flux:modal name="delete-budget" class="md:w-[400px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus budget</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">Hapus budget bulan ini?</div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    Transaksi tetap tercatat, hanya batas budget untuk kategori ini yang dihapus.
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">Batal</button>
                </flux:modal.close>
                <button type="button" wire:click="deleteBudget" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                    <span wire:loading.remove wire:target="deleteBudget">Ya, hapus</span>
                    <span wire:loading wire:target="deleteBudget">Menghapus…</span>
                </button>
            </div>
        </div>
    </flux:modal>
</div>
