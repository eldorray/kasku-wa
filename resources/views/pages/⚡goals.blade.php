<?php

use App\Services\GoalService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Target & Goals')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $form_label = '';

    #[Validate('required|integer|min:1')]
    public int|string $form_target = '';

    #[Validate('required|integer|min:0')]
    public int|string $form_current = '';

    #[Validate('nullable|string|max:32')]
    public ?string $form_due_label = null;

    #[Validate('required|string|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $form_color = '#1f8a5b';

    #[Validate('nullable|integer|exists:accounts,id')]
    public ?int $form_account_id = null;

    public ?int $deleteId = null;

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form_label = '';
        $this->form_target = '';
        $this->form_current = '';
        $this->form_due_label = null;
        $this->form_color = '#1f8a5b';
        $this->form_account_id = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);

        $this->editingId = $goal->id;
        $this->form_label = $goal->label;
        $this->form_target = (int) $goal->target;
        $this->form_current = (int) $goal->current;
        $this->form_due_label = $goal->due_label;
        $this->form_color = $goal->color;
        $this->form_account_id = $goal->account_id;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(GoalService $service): void
    {
        $this->validate();

        $payload = [
            'label' => $this->form_label,
            'target' => (int) $this->form_target,
            'current' => (int) $this->form_current,
            'due_label' => $this->form_due_label ?: null,
            'color' => $this->form_color,
            'account_id' => $this->form_account_id ?: null,
        ];

        try {
            if ($this->editingId) {
                $goal = currentHousehold()->goals()->findOrFail($this->editingId);
                $service->update($goal, Auth::user(), $payload);
                Flux::toast(variant: 'success', text: 'Goal diperbarui.');
            } else {
                $service->create(currentHousehold(), Auth::user(), $payload);
                Flux::toast(variant: 'success', text: 'Goal baru ditambahkan.');
            }
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->closeForm();
    }

    public function addProgress(int $id, int $amount, GoalService $service): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);
        try { $service->addProgress($goal, Auth::user(), $amount); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Progress goal ditambahkan.');
    }

    public function reduceProgress(int $id, int $amount, GoalService $service): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);
        try { $service->reduceProgress($goal, Auth::user(), $amount); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Progress goal dikurangi.');
    }

    public function markComplete(int $id, GoalService $service): void
    {
        $goal = currentHousehold()->goals()->findOrFail($id);
        try { $service->markComplete($goal, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Goal ditandai selesai.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = currentHousehold()->goals()->findOrFail($id)->id;
        Flux::modal('delete-goal')->show();
    }

    public function delete(GoalService $service): void
    {
        if (! $this->deleteId) {
            return;
        }

        $goal = currentHousehold()->goals()->findOrFail($this->deleteId);
        try { $service->delete($goal, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        $this->deleteId = null;
        Flux::modal('delete-goal')->close();
        Flux::toast(variant: 'success', text: 'Goal dihapus.');
    }

    public function with(GoalService $service): array
    {
        // Sync goals linked to an account so progress reflects real balance.
        $service->syncAllForHousehold(currentHousehold());

        $goals = currentHousehold()->goals()
            ->with('account')
            ->orderByRaw('CASE WHEN target > 0 THEN current / target ELSE 0 END DESC')
            ->orderBy('id')
            ->get();
        $accounts = currentHousehold()->accounts()->orderBy('id')->get();

        $totalTarget = (int) $goals->sum('target');
        $totalCurrent = (int) $goals->sum('current');
        $totalRemaining = max(0, $totalTarget - $totalCurrent);
        $overallProgress = $totalTarget > 0 ? min(100, (int) round($totalCurrent / $totalTarget * 100)) : 0;
        $completedCount = $goals->filter(fn ($goal) => (int) $goal->current >= (int) $goal->target)->count();

        return compact('goals', 'accounts', 'totalTarget', 'totalCurrent', 'totalRemaining', 'overallProgress', 'completedCount');
    }
};
?>

<div>
    <x-kasku.page-header
        eyebrow="Target & Goals"
        title="Target Tabungan"
        :sub="$goals->count() . ' target aktif · ' . $completedCount . ' selesai'">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="kasku-btn kasku-btn--primary">
                <x-kasku.icon name="plus" :size="14" /> Goal baru
            </button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid kasku-grid-4" style="margin-bottom:20px">
        <div class="kasku-card" style="background:var(--color-ink);color:var(--color-bg-elev);border-color:var(--color-ink)">
            <div class="kasku-eyebrow" style="color:rgba(255,255,255,0.6);margin-bottom:12px">Terkumpul</div>
            <div class="kasku-display" style="font-size:34px">{{ \App\Support\Money::fmt($totalCurrent) }}</div>
            <div class="kasku-mono" style="font-size:11px;color:rgba(255,255,255,0.55);margin-top:8px">dari {{ \App\Support\Money::fmt($totalTarget) }}</div>
        </div>

        <div class="kasku-card">
            <div class="kasku-eyebrow" style="margin-bottom:12px">Sisa Target</div>
            <div class="kasku-display" style="font-size:30px">{{ \App\Support\Money::fmt($totalRemaining) }}</div>
            <div style="margin-top:14px"><x-kasku.bar :value="$totalCurrent" :max="$totalTarget ?: 1" color="var(--color-pos)" /></div>
        </div>

        <div class="kasku-card">
            <div class="kasku-eyebrow" style="margin-bottom:12px">Progress Total</div>
            <div class="kasku-display" style="font-size:30px">{{ $overallProgress }}%</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">{{ $goals->count() }} goal dipantau</div>
        </div>

        <div class="kasku-card">
            <div class="kasku-eyebrow" style="margin-bottom:12px">Goal Selesai</div>
            <div class="kasku-display" style="font-size:30px">{{ $completedCount }}</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">target sudah tercapai</div>
        </div>
    </div>

    <div class="kasku-grid kasku-grid-3">
        @forelse($goals as $goal)
            @php
                $pct = $goal->target > 0 ? min(100, (int) round($goal->current / $goal->target * 100)) : 0;
                $remaining = max(0, (int) $goal->target - (int) $goal->current);
                $isComplete = $remaining === 0 && (int) $goal->target > 0;
            @endphp
            <div class="kasku-card" wire:key="goal-page-{{ $goal->id }}" style="position:relative;overflow:hidden">
                <div style="position:absolute;top:-36px;right:-36px;width:150px;height:150px;border-radius:50%;background:{{ $goal->color }};opacity:0.07"></div>

                <div style="display:flex;align-items:center;justify-content:space-between;position:relative">
                    <div class="kasku-eyebrow">{{ $goal->due_label ?: 'Tanpa deadline' }}</div>
                    <div style="display:flex;align-items:center;gap:6px">
                        <x-kasku.chip :variant="$isComplete ? 'pos' : 'neutral'">{{ $isComplete ? 'Selesai' : $pct . '%' }}</x-kasku.chip>
                        <flux:dropdown align="end">
                            <button type="button" class="kasku-icon-btn" style="border:none">
                                <x-kasku.icon name="more" />
                            </button>
                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="openEdit({{ $goal->id }})">Edit goal</flux:menu.item>
                                <flux:menu.item icon="plus" wire:click="addProgress({{ $goal->id }}, 100000)">Tambah Rp100rb</flux:menu.item>
                                <flux:menu.item icon="plus" wire:click="addProgress({{ $goal->id }}, 500000)">Tambah Rp500rb</flux:menu.item>
                                <flux:menu.item icon="minus" wire:click="reduceProgress({{ $goal->id }}, 100000)">Kurangi Rp100rb</flux:menu.item>
                                <flux:menu.item icon="check" wire:click="markComplete({{ $goal->id }})">Tandai selesai</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $goal->id }})">Hapus goal</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

                <div style="display:flex;gap:12px;align-items:center;margin-top:18px;position:relative">
                    <div style="width:46px;height:46px;border-radius:12px;background:{{ $goal->color }};color:white;display:grid;place-items:center;font-weight:600">🎯</div>
                    <div style="min-width:0">
                        <div class="kasku-display" style="font-size:22px;line-height:1.15">{{ $goal->label }}</div>
                        <div style="font-size:11px;color:var(--color-ink-3);margin-top:4px">Sisa {{ \App\Support\Money::fmt($remaining) }}</div>
                    </div>
                </div>

                <div style="margin-top:18px;margin-bottom:8px">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-ink-3);margin-bottom:8px">
                        <span class="kasku-tabular">{{ \App\Support\Money::fmt($goal->current) }}</span>
                        <span class="kasku-tabular">{{ \App\Support\Money::fmt($goal->target) }}</span>
                    </div>
                    <x-kasku.bar :value="$goal->current" :max="$goal->target" :color="$goal->color" height="5px" />
                </div>
            </div>
        @empty
            <button type="button" wire:click="openCreate" class="kasku-card" style="border-style:dashed;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--color-ink-3);min-height:240px;cursor:pointer;background:transparent;font-family:inherit;text-align:center">
                <div style="width:52px;height:52px;border-radius:14px;border:1.5px dashed var(--color-line-2);display:grid;place-items:center;margin-bottom:14px"><x-kasku.icon name="plus" :size="22" /></div>
                <div style="font-weight:500;color:var(--color-ink-2)">Buat target pertama</div>
                <div style="font-size:11px;margin-top:4px;max-width:220px">Pantau dana darurat, liburan, gadget, atau rencana finansial lainnya.</div>
            </button>
        @endforelse
    </div>

    @if($showForm)
        <div class="kasku-overlay" wire:click="closeForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="save">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingId ? 'Edit Goal' : 'Goal Baru' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    <div style="display:flex;gap:14px;align-items:center;padding:14px;background:var(--color-bg-sunken);border-radius:10px">
                        <div style="width:44px;height:44px;border-radius:11px;background:{{ $form_color }};color:white;display:grid;place-items:center;font-weight:600;font-size:14px">🎯</div>
                        <div>
                            <div class="kasku-eyebrow">Pratinjau target</div>
                            <div style="font-weight:500;font-size:14px;margin-top:2px">{{ $form_label ?: 'Nama goal' }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">
                                {{ \App\Support\Money::fmt((int) ($form_current ?: 0)) }} / {{ \App\Support\Money::fmt((int) ($form_target ?: 0)) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Nama goal</label>
                        <input type="text" wire:model.live.debounce.300ms="form_label" placeholder="Mis. Dana darurat, Liburan, Laptop baru" class="kasku-form-input" />
                        @error('form_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Target nominal (Rp)</label>
                            <input type="number" min="1" step="1000" wire:model="form_target" placeholder="10000000" class="kasku-form-input" />
                            @error('form_target')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Terkumpul saat ini (Rp)</label>
                            <input type="number" min="0" step="1000" wire:model="form_current" placeholder="2500000" class="kasku-form-input" />
                            @error('form_current')<div class="kasku-form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Deadline / catatan waktu <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                        <input type="text" wire:model.live.debounce.300ms="form_due_label" placeholder="Mis. Des 2026" class="kasku-form-input" />
                        @error('form_due_label')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Warna goal</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" wire:model.live="form_color" style="width:48px;height:38px;border-radius:8px;border:1px solid var(--color-line);cursor:pointer;padding:2px" />
                            <input type="text" wire:model.live.debounce.300ms="form_color" placeholder="#1f8a5b" class="kasku-form-input kasku-mono" style="flex:1" />
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
                <button type="button" wire:click="delete" wire:loading.attr="disabled" class="kasku-btn" style="background:var(--color-neg);color:white;border-color:var(--color-neg)">
                    <span wire:loading.remove wire:target="delete">Ya, hapus</span>
                    <span wire:loading wire:target="delete">Menghapus…</span>
                </button>
            </div>
        </div>
    </flux:modal>
</div>