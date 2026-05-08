<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\Money;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Transaksi')] class extends Component
{
    // Filter state
    public string $type = 'all';
    public string $via = 'all';
    public string $cat = 'all';

    #[Url(as: 'account')]
    public string $accountFilter = 'all';

    #[Url(as: 'by')]
    public string $creatorFilter = 'all';

    public ?int $selectedTxId = null;

    // Bulk selection state
    public bool $bulkMode = false;
    /** @var array<int, int> */
    public array $checked = [];

    // Form state
    public bool $showForm = false;
    public ?int $editingId = null;

    #[Validate('required|in:expense,income')]
    public string $form_type = 'expense';

    #[Validate('required|date')]
    public string $form_date = '';

    #[Validate('required|string|max:255')]
    public string $form_label = '';

    #[Validate('required|numeric|min:1')]
    public int|string $form_amount = '';

    #[Validate('required|exists:categories,id')]
    public ?int $form_category_id = null;

    #[Validate('required|exists:accounts,id')]
    public ?int $form_account_id = null;

    #[Validate('nullable|string|max:255')]
    public ?string $form_merchant = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $form_note = null;

    public function setFilter(string $key, string $val): void
    {
        if (in_array($key, ['type', 'via', 'cat'], true)) {
            $this->{$key} = $val;
        } elseif ($key === 'account') {
            $this->accountFilter = $val;
        } elseif ($key === 'by') {
            $this->creatorFilter = $val;
        }
        $this->selectedTxId = null;
        $this->checked = [];
    }

    public function toggleBulk(): void
    {
        $this->bulkMode = ! $this->bulkMode;
        $this->checked = [];
        $this->selectedTxId = null;
    }

    public function selectAllVisible(): void
    {
        $ids = currentHousehold()->transactions()
            ->when($this->type === 'income', fn ($q) => $q->where('type', Transaction::TYPE_INCOME))
            ->when($this->type === 'expense', fn ($q) => $q->where('type', Transaction::TYPE_EXPENSE))
            ->when($this->type === 'transfer', fn ($q) => $q->where('type', Transaction::TYPE_TRANSFER))
            ->when($this->via !== 'all', fn ($q) => $q->where('via', $this->via))
            ->when($this->cat !== 'all', fn ($q) => $q->whereHas('category', fn ($q2) => $q2->where('slug', $this->cat)))
            ->when($this->accountFilter !== 'all', fn ($q) => $q->where('account_id', (int) $this->accountFilter))
            ->when($this->creatorFilter !== 'all', fn ($q) => $q->where('user_id', (int) $this->creatorFilter))
            ->pluck('id')
            ->all();

        $allChecked = count($this->checked) === count($ids) && count($ids) > 0;
        $this->checked = $allChecked ? [] : array_map('intval', $ids);
    }

    public function clearSelection(): void
    {
        $this->checked = [];
    }

    public function confirmDeleteMany(): void
    {
        if (empty($this->checked)) {
            return;
        }
        Flux::modal('delete-tx-bulk')->show();
    }

    public function deleteMany(TransactionService $service): void
    {
        if (empty($this->checked)) {
            return;
        }
        $txs = currentHousehold()->transactions()->whereIn('id', $this->checked)->get();
        try {
            $count = $service->deleteMany($txs, Auth::user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }
        $this->checked = [];
        $this->bulkMode = false;
        Flux::modal('delete-tx-bulk')->close();
        Flux::toast(variant: 'success', text: $count.' transaksi dihapus.');
    }

    public function openTx(int $id): void
    {
        $this->selectedTxId = $id;
    }

    public function closeTx(): void
    {
        $this->selectedTxId = null;
    }

    public function mount(): void
    {
        if (request()->query('action') === 'add') {
            $this->openCreate();
        }
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->resetForm();
        $defaultAccount = currentHousehold()->accounts()->orderBy('id')->first();
        $defaultCat = Category::where('slug', 'food')->first()
            ?? Category::whereIn('type', ['expense', 'both'])->first();
        $this->form_account_id = $defaultAccount?->id;
        $this->form_category_id = $defaultCat?->id;
        $this->form_date = now()->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function updatedFormType(): void
    {
        // If the previously selected category no longer matches the new type, clear it.
        if ($this->form_category_id) {
            $cat = Category::find($this->form_category_id);
            if (! $cat || ! $cat->acceptsType($this->form_type)) {
                $this->form_category_id = null;
            }
        }
    }

    public function openEdit(int $id): void
    {
        $tx = currentHousehold()->transactions()->findOrFail($id);
        $this->editingId = $tx->id;
        $this->form_type = $tx->amount < 0 ? 'expense' : 'income';
        $this->form_date = $tx->occurred_at->format('Y-m-d\TH:i');
        $this->form_label = $tx->label;
        $this->form_amount = abs((int) $tx->amount);
        $this->form_category_id = $tx->category_id;
        $this->form_account_id = $tx->account_id;
        $this->form_merchant = $tx->merchant;
        $this->form_note = $tx->note;
        $this->selectedTxId = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(TransactionService $service): void
    {
        $data = $this->validate();

        currentHousehold()->accounts()->findOrFail((int) $this->form_account_id);

        $payload = [
            'account_id' => (int) $this->form_account_id,
            'category_id' => (int) $this->form_category_id,
            'label' => $this->form_label,
            'amount' => abs((int) $this->form_amount),
            'type' => $this->form_type,
            'via' => 'manual',
            'note' => $this->form_note,
            'merchant' => $this->form_merchant,
            'occurred_at' => Carbon::parse($this->form_date),
        ];

        try {
            if ($this->editingId) {
                $tx = currentHousehold()->transactions()->findOrFail($this->editingId);
                $service->update($tx, Auth::user(), $payload);
                Flux::toast(variant: 'success', text: 'Transaksi diperbarui.');
            } else {
                $service->create(currentHousehold(), Auth::user(), $payload);
                Flux::toast(variant: 'success', text: 'Transaksi ditambahkan.');
            }
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedTxId = $id;
        Flux::modal('delete-tx')->show();
    }

    public function delete(TransactionService $service): void
    {
        if (! $this->selectedTxId) {
            return;
        }
        $tx = currentHousehold()->transactions()->findOrFail($this->selectedTxId);
        try {
            $service->delete($tx, Auth::user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }
        $this->selectedTxId = null;
        Flux::modal('delete-tx')->close();
        Flux::toast(variant: 'success', text: 'Transaksi dihapus.');
    }

    private function resetForm(): void
    {
        $this->form_type = 'expense';
        $this->form_date = now()->format('Y-m-d\TH:i');
        $this->form_label = '';
        $this->form_amount = '';
        $this->form_category_id = null;
        $this->form_account_id = null;
        $this->form_merchant = null;
        $this->form_note = null;
        $this->resetValidation();
    }

    public function with(): array
    {
        $user = Auth::user();
        $household = currentHousehold();
        $query = $household->transactions()->with(['category', 'account', 'creator']);

        if ($this->type === 'income') {
            $query->where('type', Transaction::TYPE_INCOME);
        } elseif ($this->type === 'expense') {
            $query->where('type', Transaction::TYPE_EXPENSE);
        } elseif ($this->type === 'transfer') {
            $query->where('type', Transaction::TYPE_TRANSFER);
        }
        if ($this->via !== 'all') {
            $query->where('via', $this->via);
        }
        if ($this->cat !== 'all') {
            $catId = Category::where('slug', $this->cat)->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
            }
        }
        if ($this->accountFilter !== 'all') {
            $query->where('account_id', (int) $this->accountFilter);
        }
        if ($this->creatorFilter !== 'all') {
            $query->where('user_id', (int) $this->creatorFilter);
        }

        $tx = $query->orderByDesc('occurred_at')->get();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $totalIn = (int) $household->transactions()
            ->where('type', Transaction::TYPE_INCOME)
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $totalOut = (int) $household->transactions()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $totalBalance = (int) $household->accounts()->sum('balance');

        $grouped = $tx->groupBy(fn ($t) => $t->occurred_at->format('Y-m-d'));

        $allCategories = Category::all();
        $allAccounts = $household->accounts()->orderBy('id')->get();
        $householdMembers = $household->members()->get();

        $selected = $this->selectedTxId
            ? $household->transactions()->with(['category', 'account', 'creator'])->find($this->selectedTxId)
            : null;

        return compact('tx', 'totalIn', 'totalOut', 'totalBalance', 'grouped', 'allCategories', 'allAccounts', 'selected', 'householdMembers');
    }
};
?>

<div>
    {{-- Mobile appbar --}}
    <div class="kasku-mobile-appbar">
        <div style="flex:1">
            <div class="kasku-mobile-appbar-title">Transaksi</div>
            <div class="kasku-mobile-appbar-sub">{{ $tx->count() }} transaksi</div>
        </div>
        <div class="kasku-mobile-appbar-actions">
            <button type="button" wire:click="toggleBulk" class="kasku-mobile-appbar-icon" aria-label="Pilih banyak">
                <x-kasku.icon name="list" :size="16" />
            </button>
            <button type="button" wire:click="openCreate" class="kasku-mobile-appbar-icon" aria-label="Tambah manual">
                <x-kasku.icon name="plus" :size="16" />
            </button>
        </div>
    </div>

    <x-kasku.page-header
        class="kasku-desktop-only"
        eyebrow="Transaksi"
        title="Semua Transaksi"
        :sub="$tx->count() . ' transaksi · 70% dicatat otomatis dari WhatsApp'">
        <x-slot:actions>
            <button type="button" wire:click="toggleBulk" class="kasku-btn @if($bulkMode) kasku-btn--primary @endif">
                <x-kasku.icon name="list" :size="14" />
                {{ $bulkMode ? 'Selesai' : 'Pilih banyak' }}
            </button>
            <button type="button" class="kasku-btn"><x-kasku.icon name="filter" :size="14" /> Filter lanjutan</button>
            <button type="button" wire:click="openCreate" class="kasku-btn kasku-btn--primary"><x-kasku.icon name="plus" :size="14" /> Tambah manual</button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid kasku-grid-3" style="margin-bottom:24px">
        <div class="kasku-card">
            <div class="kasku-eyebrow">Pemasukan bulan ini</div>
            <div class="kasku-display" style="font-size:24px;margin-top:8px;color:var(--color-pos)">{{ \App\Support\Money::fmt($totalIn) }}</div>
        </div>
        <div class="kasku-card">
            <div class="kasku-eyebrow">Pengeluaran</div>
            <div class="kasku-display" style="font-size:24px;margin-top:8px;color:var(--color-neg)">{{ \App\Support\Money::fmt($totalOut) }}</div>
        </div>
        <div class="kasku-card">
            <div class="kasku-eyebrow">Saldo</div>
            <div class="kasku-display" style="font-size:24px;margin-top:8px">{{ \App\Support\Money::fmt($totalBalance) }}</div>
        </div>
    </div>

    @if($accountFilter !== 'all')
        @php $filteredAccount = $allAccounts->firstWhere('id', (int) $accountFilter); @endphp
        @if($filteredAccount)
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--color-wa-bg);border-radius:10px;margin-bottom:16px">
                <div style="width:28px;height:28px;border-radius:8px;background:{{ $filteredAccount->color }};color:white;display:grid;place-items:center;font-weight:600;font-size:11px">{{ mb_strtoupper(mb_substr($filteredAccount->label, 0, 2)) }}</div>
                <div style="font-size:13px">
                    Memfilter transaksi akun <b>{{ $filteredAccount->label }}</b>
                </div>
                <button type="button" wire:click="setFilter('account', 'all')" style="margin-left:auto;background:transparent;border:none;cursor:pointer;color:var(--color-wa-deep);font-size:12px;display:flex;align-items:center;gap:4px">
                    <x-kasku.icon name="x" :size="12" /> Hapus filter
                </button>
            </div>
        @endif
    @endif

    <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        <div class="kasku-tabs">
            @foreach([['all','Semua'],['expense','Pengeluaran'],['income','Pemasukan']] as [$k, $l])
                <button type="button" wire:click="setFilter('type', '{{ $k }}')" class="kasku-tab @if($type === $k) is-active @endif">{{ $l }}</button>
            @endforeach
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button type="button" wire:click="setFilter('via', 'all')" class="kasku-pill @if($via === 'all') is-active @endif">Semua sumber</button>
            <button type="button" wire:click="setFilter('via', 'wa')" class="kasku-pill @if($via === 'wa') is-active @endif">💬 Chat WA</button>
            <button type="button" wire:click="setFilter('via', 'receipt')" class="kasku-pill @if($via === 'receipt') is-active @endif">📷 Foto struk</button>
            <button type="button" wire:click="setFilter('via', 'manual')" class="kasku-pill @if($via === 'manual') is-active @endif">✍️ Manual</button>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button type="button" wire:click="setFilter('cat', 'all')" class="kasku-pill @if($cat === 'all') is-active @endif">Semua kategori</button>
            @foreach($allCategories->take(5) as $c)
                <button type="button" wire:click="setFilter('cat', '{{ $c->slug }}')" class="kasku-pill @if($cat === $c->slug) is-active @endif">{{ $c->emoji }} {{ $c->label }}</button>
            @endforeach
        </div>
    </div>

    @if($bulkMode)
        <div class="kasku-card" style="padding:12px 16px;margin-bottom:12px;background:var(--color-ink);color:var(--color-bg-elev);border-color:var(--color-ink);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:12px">
                <button type="button" wire:click="selectAllVisible" class="kasku-btn" style="background:transparent;color:var(--color-bg-elev);border-color:rgba(255,255,255,0.2)">
                    {{ count($checked) === $tx->count() && $tx->count() > 0 ? 'Hapus pilihan' : 'Pilih semua ('.$tx->count().')' }}
                </button>
                <div style="font-size:13px">
                    <b>{{ count($checked) }}</b> dipilih
                </div>
            </div>
            <div style="display:flex;gap:10px">
                @if(count($checked) > 0)
                    <button type="button" wire:click="clearSelection" class="kasku-btn" style="background:transparent;color:var(--color-bg-elev);border-color:rgba(255,255,255,0.2)">Reset</button>
                    <button type="button" wire:click="confirmDeleteMany" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                        <x-kasku.icon name="x" :size="14" /> Hapus {{ count($checked) }} transaksi
                    </button>
                @endif
            </div>
        </div>
    @endif

    <div class="kasku-card" style="padding:0;overflow:hidden">
        @forelse($grouped as $day => $items)
            @php
                $dIn = (int) $items->where('amount', '>', 0)->sum('amount');
                $dOut = (int) $items->where('amount', '<', 0)->sum('amount');
            @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:var(--color-bg-sunken);border-top:1px solid var(--color-line);border-bottom:1px solid var(--color-line);font-size:12px">
                <div style="font-weight:500">{{ \App\Support\Charts::formatDay($day) }}</div>
                <div style="display:flex;gap:12px;color:var(--color-ink-3);font-size:11px" class="kasku-tabular">
                    @if($dIn > 0)<span style="color:var(--color-pos)">+{{ \App\Support\Money::fmt($dIn) }}</span>@endif
                    @if($dOut < 0)<span style="color:var(--color-neg)">{{ \App\Support\Money::fmt($dOut) }}</span>@endif
                </div>
            </div>
            @foreach($items as $t)
                @php $isChecked = in_array($t->id, $checked, true); @endphp
                <label
                    wire:key="tx-{{ $t->id }}"
                    class="kasku-tx-row"
                    @if(! $bulkMode) wire:click="openTx({{ $t->id }})" @endif
                    style="display:grid;grid-template-columns:{{ $bulkMode ? '32px ' : '' }}44px minmax(0,1fr) auto auto;gap:16px;align-items:center;padding:14px 20px;border-bottom:1px solid var(--color-line);cursor:pointer;{{ $isChecked ? 'background:var(--color-bg-sunken)' : '' }}">
                    @if($bulkMode)
                        <input
                            type="checkbox"
                            value="{{ $t->id }}"
                            wire:model.live="checked"
                            wire:click.stop
                            style="width:18px;height:18px;cursor:pointer;accent-color:var(--color-ink)" />
                    @endif
                    <x-kasku.cat-icon :category="$t->category" />
                    <div style="min-width:0">
                        <div style="font-weight:500;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->label }}</div>
                        <div style="font-size:11px;color:var(--color-ink-3);margin-top:3px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <span>{{ $t->category->label }}</span><span style="opacity:0.4">·</span>
                            <span>{{ $t->account->label }}</span>
                            <span class="kasku-desktop-only" style="opacity:0.4">·</span>
                            <span class="kasku-desktop-only" title="Dicatat oleh">👤 {{ $t->creator?->name ?? 'User dihapus' }}</span>
                            <span class="kasku-desktop-only" style="opacity:0.4">·</span>
                            <span class="kasku-mono kasku-desktop-only">{{ $t->occurred_at->format('H:i') }}</span>
                        </div>
                    </div>
                    <div class="kasku-desktop-only"><x-kasku.via-chip :via="$t->via" /></div>
                    <div class="kasku-money @if($t->amount < 0) kasku-money--neg @else kasku-money--pos @endif" style="font-weight:500;font-size:13px;text-align:right;white-space:nowrap">{{ \App\Support\Money::fmtShort($t->amount) }}</div>
                </label>
            @endforeach
        @empty
            <div style="padding:48px;text-align:center;color:var(--color-ink-3)">Tidak ada transaksi cocok dengan filter.</div>
        @endforelse
    </div>

    {{-- Detail drawer --}}
    @if($selected && ! $showForm)
        @php $sc = $selected->category; $sa = $selected->account; @endphp
        <div class="kasku-overlay" wire:click="closeTx" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                <div class="kasku-eyebrow">Detail Transaksi</div>
                <button type="button" class="kasku-icon-btn" wire:click="closeTx"><x-kasku.icon name="x" /></button>
            </div>
            <div style="padding:24px">
                <div class="kasku-cat-icon" style="width:56px;height:56px;border-radius:14px;background:{{ $sc->bg }};color:{{ $sc->color }};font-size:24px">{{ $sc->emoji }}</div>
                <div class="kasku-display" style="font-size:36px;margin-top:16px;margin-bottom:4px;color:{{ $selected->amount > 0 ? 'var(--color-pos)' : 'var(--color-ink)' }}">{{ \App\Support\Money::fmt($selected->amount) }}</div>
                <div style="font-size:16px;font-weight:500">{{ $selected->label }}</div>
                <div style="font-size:12px;color:var(--color-ink-3);margin-top:4px">{{ $selected->occurred_at->locale('id')->isoFormat('dddd, D MMMM YYYY · HH:mm') }}</div>

                <div class="kasku-divider"></div>

                <div style="display:flex;flex-direction:column;gap:14px;font-size:13px">
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Tipe</div><div style="font-weight:500">{{ $selected->amount > 0 ? 'Pemasukan' : 'Pengeluaran' }}</div></div>
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Kategori</div><div style="font-weight:500"><span style="margin-right:6px">{{ $sc->emoji }}</span>{{ $sc->label }}</div></div>
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Akun</div><div style="font-weight:500;display:inline-flex;align-items:center;gap:8px"><span style="width:10px;height:10px;border-radius:3px;background:{{ $sa->color }}"></span>{{ $sa->label }} @if($sa->last_four)<span style="color:var(--color-ink-3);font-size:11px">·••{{ $sa->last_four }}</span>@endif</div></div>
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Diinput oleh</div><div style="font-weight:500">{{ $selected->user?->name ?? 'User dihapus' }}</div></div>
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Merchant</div><div style="font-weight:500">{{ $selected->merchant ?? '—' }}</div></div>
                    <div style="display:flex;align-items:center;justify-content:space-between"><div style="color:var(--color-ink-3)">Sumber input</div><div style="font-weight:500"><x-kasku.via-chip :via="$selected->via" /></div></div>
                </div>

                @if($selected->via === 'wa')
                    <div style="background:var(--color-wa-bg);padding:14px;border-radius:10px;margin-top:20px">
                        <div style="font-size:11px;color:var(--color-wa-deep);margin-bottom:6px;display:flex;align-items:center;gap:6px"><x-kasku.icon name="wa" :size="11" /> Pesan asli</div>
                        <div class="kasku-mono" style="font-size:13px;color:var(--color-wa-ink)">{{ $selected->note }}</div>
                        <div style="font-size:11px;color:var(--color-wa-deep);margin-top:8px;display:flex;align-items:center;gap:6px"><x-kasku.icon name="sparkle" :size="11" /> Diparse otomatis dengan kepercayaan 96%</div>
                    </div>
                @endif

                @if($selected->note && $selected->via !== 'wa')
                    <div class="kasku-divider"></div>
                    <div class="kasku-eyebrow" style="margin-bottom:10px">Catatan</div>
                    <div style="font-size:12px;color:var(--color-ink-2)">{{ $selected->note }}</div>
                @endif

                <div style="display:flex;gap:10px;margin-top:28px">
                    <button type="button" wire:click="openEdit({{ $selected->id }})" class="kasku-btn" style="flex:1;justify-content:center">Edit</button>
                    <button type="button" wire:click="confirmDelete({{ $selected->id }})" class="kasku-btn" style="flex:1;justify-content:center;color:var(--color-neg);border-color:transparent">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Create / Edit drawer --}}
    @if($showForm)
        <div class="kasku-overlay" wire:click="closeForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="save">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingId ? 'Edit Transaksi' : 'Tambah Transaksi' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    {{-- Type toggle --}}
                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:8px">Tipe</label>
                        <div class="kasku-tabs" style="width:100%">
                            <button type="button" wire:click="$set('form_type', 'expense')" class="kasku-tab @if($form_type === 'expense') is-active @endif" style="flex:1">Pengeluaran</button>
                            <button type="button" wire:click="$set('form_type', 'income')" class="kasku-tab @if($form_type === 'income') is-active @endif" style="flex:1">Pemasukan</button>
                        </div>
                    </div>

                    {{-- Date + amount row --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Tanggal & jam</label>
                            <input type="datetime-local" wire:model="form_date" class="kasku-form-input" />
                            @error('form_date')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Jumlah (Rp)</label>
                            <input type="number" min="1" step="1" wire:model="form_amount" placeholder="50000" class="kasku-form-input" />
                            @error('form_amount')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Label --}}
                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Judul / label</label>
                        <input type="text" wire:model="form_label" placeholder="Mis. Kopi Tuku, Invoice #2026-014" class="kasku-form-input" />
                        @error('form_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    {{-- Category + account row --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Kategori</label>
                            <select wire:model="form_category_id" class="kasku-form-input">
                                <option value="">— Pilih —</option>
                                @foreach($allCategories->filter(fn($c) => $c->acceptsType($form_type)) as $c)
                                    <option value="{{ $c->id }}">{{ $c->emoji }} {{ $c->label }}</option>
                                @endforeach
                            </select>
                            @error('form_category_id')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Akun</label>
                            <select wire:model="form_account_id" class="kasku-form-input">
                                <option value="">— Pilih —</option>
                                @foreach($allAccounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->label }} ({{ $a->type }})</option>
                                @endforeach
                            </select>
                            @error('form_account_id')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Merchant --}}
                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Merchant <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                        <input type="text" wire:model="form_merchant" placeholder="Mis. Tokopedia, Indomaret" class="kasku-form-input" />
                    </div>

                    {{-- Note --}}
                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Catatan <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                        <textarea wire:model="form_note" rows="3" placeholder="Detail tambahan…" class="kasku-form-input" style="resize:vertical;font-family:inherit"></textarea>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="button" wire:click="closeForm" class="kasku-btn" style="flex:1;justify-content:center">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="kasku-btn kasku-btn--primary" style="flex:1;justify-content:center">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Tambah transaksi' }}</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Bulk delete confirmation modal --}}
    <flux:modal name="delete-tx-bulk" class="md:w-[400px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus banyak transaksi</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">Hapus {{ count($checked) }} transaksi sekaligus?</div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    Saldo semua akun terkait akan disesuaikan kembali secara otomatis. Tindakan ini tidak dapat dibatalkan.
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">Batal</button>
                </flux:modal.close>
                <button type="button" wire:click="deleteMany" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                    <span wire:loading.remove wire:target="deleteMany">Ya, hapus {{ count($checked) }}</span>
                    <span wire:loading wire:target="deleteMany">Menghapus…</span>
                </button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal name="delete-tx" class="md:w-[400px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus transaksi</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">Yakin ingin menghapus?</div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    Transaksi akan dihapus permanen dan saldo akun terkait akan disesuaikan kembali. Tindakan ini tidak dapat dibatalkan.
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <flux:modal.close>
                    <button type="button" class="kasku-btn">Batal</button>
                </flux:modal.close>
                <button type="button" wire:click="delete" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                    <span wire:loading.remove wire:target="delete">Ya, hapus</span>
                    <span wire:loading wire:target="delete">Menghapus…</span>
                </button>
            </div>
        </div>
    </flux:modal>
</div>
