<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('User')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $form_name = '';

    public string $form_email = '';

    public ?string $form_phone = null;

    public string $form_password = '';

    public string $form_password_confirmation = '';

    public ?int $deleteId = null;

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form_name = '';
        $this->form_email = '';
        $this->form_phone = null;
        $this->form_password = '';
        $this->form_password_confirmation = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::query()->findOrFail($id);

        $this->editingId = $user->id;
        $this->form_name = $user->name;
        $this->form_email = $user->email;
        $this->form_phone = $user->phone;
        $this->form_password = '';
        $this->form_password_confirmation = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'form_name' => ['required', 'string', 'max:255'],
            'form_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form_phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($this->editingId)],
            'form_password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ], [
            'form_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'form_password.required' => 'Password wajib diisi untuk user baru.',
        ]);

        $payload = [
            'name' => $this->form_name,
            'email' => $this->form_email,
            'phone' => $this->form_phone ?: null,
        ];

        if ($this->form_password !== '') {
            $payload['password'] = Hash::make($this->form_password);
        }

        if ($this->editingId) {
            User::query()->findOrFail($this->editingId)->update($payload);
            Flux::toast(variant: 'success', text: 'User diperbarui.');
        } else {
            User::query()->create($payload);
            Flux::toast(variant: 'success', text: 'User baru ditambahkan.');
        }

        $this->closeForm();
    }

    public function confirmDelete(int $id): void
    {
        $user = User::query()->findOrFail($id);

        if ($user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: 'Tidak bisa menghapus user yang sedang login.');
            return;
        }

        $this->deleteId = $user->id;
        Flux::modal('delete-user')->show();
    }

    public function delete(): void
    {
        if (! $this->deleteId) {
            return;
        }

        $user = User::query()->findOrFail($this->deleteId);

        if ($user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: 'Tidak bisa menghapus user yang sedang login.');
            return;
        }

        $user->delete();
        $this->deleteId = null;
        Flux::modal('delete-user')->close();
        Flux::toast(variant: 'success', text: 'User dihapus.');
    }

    public function with(): array
    {
        $users = User::query()
            ->withCount(['accounts', 'transactions', 'goals'])
            ->orderBy('name')
            ->get();

        $totalUsers = $users->count();
        $verifiedUsers = $users->whereNotNull('email_verified_at')->count();
        $withPhone = $users->whereNotNull('phone')->count();

        return compact('users', 'totalUsers', 'verifiedUsers', 'withPhone');
    }
};
?>

<div>
    <x-kasku.page-header
        eyebrow="User"
        title="Manajemen User"
        :sub="$totalUsers . ' user terdaftar · ' . $withPhone . ' punya nomor WhatsApp'">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="kasku-btn kasku-btn--primary">
                <x-kasku.icon name="plus" :size="14" /> User baru
            </button>
        </x-slot:actions>
    </x-kasku.page-header>

    <div class="kasku-grid kasku-grid-3" style="margin-bottom:20px">
        <div class="kasku-card" style="background:var(--color-ink);color:var(--color-bg-elev);border-color:var(--color-ink)">
            <div class="kasku-eyebrow" style="color:rgba(255,255,255,0.6);margin-bottom:12px">Total User</div>
            <div class="kasku-display" style="font-size:36px">{{ $totalUsers }}</div>
            <div class="kasku-mono" style="font-size:11px;color:rgba(255,255,255,0.55);margin-top:8px">akun pengguna aplikasi</div>
        </div>
        <div class="kasku-card">
            <div class="kasku-eyebrow" style="margin-bottom:12px">Email Terverifikasi</div>
            <div class="kasku-display" style="font-size:30px">{{ $verifiedUsers }}</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">user sudah verifikasi email</div>
        </div>
        <div class="kasku-card">
            <div class="kasku-eyebrow" style="margin-bottom:12px">WhatsApp Aktif</div>
            <div class="kasku-display" style="font-size:30px">{{ $withPhone }}</div>
            <div style="font-size:11px;color:var(--color-ink-3);margin-top:8px">user punya nomor telepon</div>
        </div>
    </div>

    <div class="kasku-card" style="padding:0">
        <div class="kasku-card-hd" style="padding:20px;margin-bottom:0">
            <div>
                <div class="kasku-card-title">Daftar User</div>
                <div class="kasku-card-sub">Tambah, edit, dan hapus user aplikasi</div>
            </div>
        </div>

        <table class="kasku-tbl">
            <thead>
                <tr>
                    <th style="text-align:left">User</th>
                    <th style="text-align:left">Kontak</th>
                    <th style="text-align:left">Data</th>
                    <th style="text-align:left">Dibuat</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:12px">
                                <div class="kasku-avatar">{{ $user->initials() }}</div>
                                <div>
                                    <div style="font-weight:500">
                                        {{ $user->name }}
                                        @if($user->is(Auth::user()))
                                            <x-kasku.chip variant="pos" style="margin-left:6px">Anda</x-kasku.chip>
                                        @endif
                                    </div>
                                    <div class="kasku-mono" style="font-size:11px;color:var(--color-ink-3);margin-top:2px">ID #{{ $user->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px">{{ $user->email }}</div>
                            <div class="kasku-mono" style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $user->phone ?: 'Belum ada nomor' }}</div>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <x-kasku.chip>{{ $user->accounts_count }} akun</x-kasku.chip>
                                <x-kasku.chip>{{ $user->transactions_count }} tx</x-kasku.chip>
                                <x-kasku.chip>{{ $user->goals_count }} goal</x-kasku.chip>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px">{{ $user->created_at?->locale('id')->isoFormat('D MMM YYYY') }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $user->email_verified_at ? 'Email verified' : 'Belum verified' }}</div>
                        </td>
                        <td style="text-align:right">
                            <flux:dropdown align="end">
                                <button type="button" class="kasku-icon-btn" style="border:none">
                                    <x-kasku.icon name="more" />
                                </button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEdit({{ $user->id }})">Edit user</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">Hapus user</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($showForm)
        <div class="kasku-overlay" wire:click="closeForm" style="position:fixed"></div>
        <div class="kasku-drawer" style="position:fixed">
            <form wire:submit="save">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:20px;border-bottom:1px solid var(--color-line)">
                    <div class="kasku-eyebrow">{{ $editingId ? 'Edit User' : 'User Baru' }}</div>
                    <button type="button" class="kasku-icon-btn" wire:click="closeForm"><x-kasku.icon name="x" /></button>
                </div>

                <div style="padding:24px;display:flex;flex-direction:column;gap:18px">
                    <div style="display:flex;gap:14px;align-items:center;padding:14px;background:var(--color-bg-sunken);border-radius:10px">
                        <div class="kasku-avatar">{{ $form_name ? \Illuminate\Support\Str::of($form_name)->explode(' ')->take(2)->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))->implode('') : 'U' }}</div>
                        <div>
                            <div class="kasku-eyebrow">Pratinjau user</div>
                            <div style="font-weight:500;font-size:14px;margin-top:2px">{{ $form_name ?: 'Nama user' }}</div>
                            <div style="font-size:11px;color:var(--color-ink-3);margin-top:2px">{{ $form_email ?: 'email@domain.com' }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Nama</label>
                        <input type="text" wire:model.live.debounce.300ms="form_name" placeholder="Nama lengkap" class="kasku-form-input" />
                        @error('form_name')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Email</label>
                        <input type="email" wire:model.live.debounce.300ms="form_email" placeholder="nama@email.com" class="kasku-form-input" />
                        @error('form_email')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Nomor WhatsApp <span style="color:var(--color-ink-3);text-transform:none;letter-spacing:normal">(opsional)</span></label>
                        <input type="text" wire:model.live.debounce.300ms="form_phone" placeholder="62812xxxx" class="kasku-form-input kasku-mono" />
                        @error('form_phone')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Password {{ $editingId ? '(kosongkan jika tidak diubah)' : '' }}</label>
                        <input type="password" wire:model="form_password" placeholder="Minimal 8 karakter" class="kasku-form-input" />
                        @error('form_password')<div class="kasku-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Konfirmasi password</label>
                        <input type="password" wire:model="form_password_confirmation" placeholder="Ulangi password" class="kasku-form-input" />
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

    <flux:modal name="delete-user" class="md:w-[420px]">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div>
                <div class="kasku-eyebrow" style="color:var(--color-neg)">Hapus user</div>
                <div style="font-size:18px;font-weight:500;margin-top:6px">Yakin hapus user ini?</div>
                <div style="font-size:13px;color:var(--color-ink-3);margin-top:6px;line-height:1.5">
                    User dan data relasinya dapat ikut terdampak. User yang sedang login tidak bisa menghapus akunnya sendiri dari halaman ini.
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