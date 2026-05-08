<?php

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Services\HouseholdService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Household settings')] class extends Component
{
    #[Validate('required|string|min:2|max:64')]
    public string $newHouseholdName = '';

    public ?int $invitingHouseholdId = null;

    #[Validate('required|in:member,viewer')]
    public string $inviteRole = 'member';

    #[Validate('required|string|max:128')]
    public string $inviteQuery = '';

    public ?int $confirmDeleteId = null;

    public function createHousehold(HouseholdService $service): void
    {
        $this->validateOnly('newHouseholdName');

        try {
            $household = $service->create(Auth::user(), trim($this->newHouseholdName));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }

        $this->newHouseholdName = '';
        Flux::toast(variant: 'success', text: 'Household '.$household->name.' dibuat.');
    }

    public function switchTo(int $householdId, HouseholdService $service): void
    {
        $h = Auth::user()->households()->where('households.id', $householdId)->firstOrFail();
        try { $service->switchTo(Auth::user(), $h); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Aktif: '.$h->name);
        $this->redirectRoute('households.edit');
    }

    public function openInvite(int $householdId): void
    {
        $h = Auth::user()->households()->where('households.id', $householdId)->firstOrFail();
        if (! $h->isOwner(Auth::user())) {
            Flux::toast(variant: 'danger', text: 'Hanya owner yang boleh undang.');
            return;
        }
        $this->invitingHouseholdId = $householdId;
        $this->inviteRole = 'member';
        $this->inviteQuery = '';
        $this->resetValidation();
        Flux::modal('invite-member')->show();
    }

    public function sendInvite(HouseholdService $service): void
    {
        if (! $this->invitingHouseholdId) {
            return;
        }
        $this->validateOnly('inviteRole');
        $this->validateOnly('inviteQuery');

        $h = Auth::user()->households()->where('households.id', $this->invitingHouseholdId)->firstOrFail();
        $target = $service->findUserByEmailOrPhone($this->inviteQuery);
        if (! $target) {
            Flux::toast(variant: 'danger', text: 'User dengan email/HP tersebut tidak ditemukan. Pastikan sudah terdaftar.');
            return;
        }

        try {
            $service->inviteUser($h, Auth::user(), $target, $this->inviteRole);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            return;
        }

        Flux::toast(variant: 'success', text: 'Undangan dikirim ke '.$target->name.'.');
        $this->inviteQuery = '';
        Flux::modal('invite-member')->close();
    }

    public function cancelInvite(int $inviteId, HouseholdService $service): void
    {
        $invite = HouseholdInvite::with('household')->findOrFail($inviteId);
        try { $service->cancelInvite($invite, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Undangan dibatalkan.');
    }

    public function acceptInvite(int $inviteId, HouseholdService $service): void
    {
        $invite = HouseholdInvite::findOrFail($inviteId);
        try { $h = $service->acceptInvite($invite, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Bergabung ke '.$h->name.'. Klik "Aktifkan" untuk pindah ke household ini.');
    }

    public function rejectInvite(int $inviteId, HouseholdService $service): void
    {
        $invite = HouseholdInvite::findOrFail($inviteId);
        try { $service->rejectInvite($invite, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Undangan ditolak.');
    }

    public function removeMember(int $householdId, int $userId, HouseholdService $service): void
    {
        $h = Auth::user()->households()->where('households.id', $householdId)->firstOrFail();
        $target = $h->members()->where('users.id', $userId)->firstOrFail();
        try { $service->removeMember($h, Auth::user(), $target); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        Flux::toast(variant: 'success', text: 'Anggota dikeluarkan.');
    }

    public function confirmDeleteHousehold(int $householdId): void
    {
        $this->confirmDeleteId = $householdId;
        Flux::modal('delete-household')->show();
    }

    public function deleteHousehold(HouseholdService $service): void
    {
        if (! $this->confirmDeleteId) {
            return;
        }
        $h = Auth::user()->households()->where('households.id', $this->confirmDeleteId)->firstOrFail();
        try { $service->delete($h, Auth::user()); }
        catch (\InvalidArgumentException $e) { Flux::toast(variant: 'danger', text: $e->getMessage()); return; }
        $this->confirmDeleteId = null;
        Flux::modal('delete-household')->close();
        Flux::toast(variant: 'success', text: 'Household dihapus.');
    }

    public function with(HouseholdService $service): array
    {
        $user = Auth::user();
        $households = $user->households()->with(['members'])->orderBy('households.id')->get();
        $current = $user->resolveHousehold();
        $incomingInvites = $service->pendingInvitesFor($user);
        $sentByHousehold = [];
        foreach ($households as $h) {
            if ($h->isOwner($user)) {
                $sentByHousehold[$h->id] = $service->pendingInvitesFrom($h);
            }
        }

        return compact('households', 'current', 'incomingInvites', 'sentByHousehold');
    }
};
?>

<x-pages::settings.layout :heading="__('Household')" :subheading="__('Kelola household Anda — pribadi, keluarga, dst.')">
    <div style="display:flex;flex-direction:column;gap:20px">
        {{-- Inbox: undangan masuk --}}
        @if($incomingInvites->isNotEmpty())
            <div class="kasku-card" style="border-color:var(--color-info);background:#eff6ff">
                <div class="kasku-card-title" style="font-size:14px;margin-bottom:12px">📬 Undangan masuk ({{ $incomingInvites->count() }})</div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    @foreach($incomingInvites as $inv)
                        <div wire:key="inbox-{{ $inv->id }}" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:white;border-radius:8px;border:1px solid var(--color-line)">
                            <div>
                                <div style="font-weight:500">{{ $inv->inviter?->name }} → <b>{{ $inv->household->name }}</b></div>
                                <div style="font-size:11px;color:var(--color-ink-3)">Sebagai {{ $inv->role }} · berlaku sampai {{ $inv->expires_at->format('d M Y H:i') }}</div>
                            </div>
                            <div style="display:flex;gap:6px">
                                <button wire:click="acceptInvite({{ $inv->id }})" class="kasku-btn kasku-btn--primary" style="font-size:12px">Terima</button>
                                <button wire:click="rejectInvite({{ $inv->id }})" class="kasku-btn" style="font-size:12px;color:var(--color-neg)">Tolak</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Buat household baru --}}
        <div class="kasku-card" style="background:var(--color-bg-soft)">
            <div class="kasku-card-title" style="font-size:14px;margin-bottom:12px">Buat household baru</div>
            <form wire:submit="createHousehold" style="display:flex;gap:8px">
                <input type="text" wire:model="newHouseholdName" placeholder="Mis. Keluarga Fahmi" class="kasku-form-input" style="flex:1" />
                <button type="submit" class="kasku-btn kasku-btn--primary">Buat</button>
            </form>
            @error('newHouseholdName')<div class="kasku-form-error">{{ $message }}</div>@enderror
        </div>

        {{-- Daftar households --}}
        @foreach($households as $h)
            @php
                $myRole = $h->pivot->role ?? null;
                $isCurrent = $current && $current->id === $h->id;
                $isOwner = $myRole === 'owner';
                $sent = $sentByHousehold[$h->id] ?? collect();
            @endphp
            <div class="kasku-card" wire:key="hh-{{ $h->id }}">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div>
                        <div style="font-weight:600;font-size:15px">{{ $h->name }}
                            @if($isCurrent) <span style="font-size:11px;color:var(--color-pos);margin-left:6px">● aktif</span>@endif
                        </div>
                        <div style="font-size:11px;color:var(--color-ink-3)">Role Anda: {{ $myRole }} · {{ $h->members->count() }} anggota</div>
                    </div>
                    <div style="display:flex;gap:8px">
                        @if(! $isCurrent)
                            <button wire:click="switchTo({{ $h->id }})" class="kasku-btn">Aktifkan</button>
                        @endif
                        @if($isOwner)
                            <button wire:click="openInvite({{ $h->id }})" class="kasku-btn">Undang anggota</button>
                            <button wire:click="confirmDeleteHousehold({{ $h->id }})" class="kasku-btn" style="color:var(--color-neg)">Hapus</button>
                        @endif
                    </div>
                </div>

                {{-- Members --}}
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach($h->members as $m)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border:1px solid var(--color-line);border-radius:8px">
                            <div>
                                <span style="font-weight:500">{{ $m->name }}</span>
                                <span style="font-size:11px;color:var(--color-ink-3);margin-left:8px">{{ $m->pivot->role }}</span>
                                @if($m->id === auth()->id()) <span style="font-size:11px;color:var(--color-info);margin-left:6px">(Anda)</span>@endif
                            </div>
                            @if($isOwner && $m->id !== auth()->id() && $m->pivot->role !== 'owner')
                                <button wire:click="removeMember({{ $h->id }}, {{ $m->id }})" class="kasku-btn" style="color:var(--color-neg);font-size:12px">Keluarkan</button>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Sent invites (owner-only) --}}
                @if($isOwner && $sent->isNotEmpty())
                    <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--color-line)">
                        <div class="kasku-eyebrow" style="margin-bottom:8px">Undangan terkirim — menunggu konfirmasi</div>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            @foreach($sent as $inv)
                                <div wire:key="sent-{{ $inv->id }}" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border:1px dashed var(--color-line-2);border-radius:8px;background:var(--color-bg-soft)">
                                    <div>
                                        <span style="font-weight:500">{{ $inv->invitee?->name ?? $inv->invited_email ?? $inv->invited_phone }}</span>
                                        <span style="font-size:11px;color:var(--color-ink-3);margin-left:8px">{{ $inv->role }} · expires {{ $inv->expires_at->format('d M') }}</span>
                                    </div>
                                    <button wire:click="cancelInvite({{ $inv->id }})" class="kasku-btn" style="color:var(--color-neg);font-size:12px">Batalkan</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Invite modal --}}
    <flux:modal name="invite-member">
        <div style="padding:16px">
            <div class="kasku-card-title" style="font-size:14px;margin-bottom:12px">Undang anggota</div>
            <p style="font-size:12px;color:var(--color-ink-3);margin-bottom:12px">User yang diundang harus sudah terdaftar di Kasku. Masukkan email atau nomor HP yang sama persis seperti yang ia daftarkan.</p>
            <div style="margin-bottom:12px">
                <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Email atau nomor HP</label>
                <input type="text" wire:model="inviteQuery" placeholder="istri@example.com atau 0812-..." class="kasku-form-input" />
                @error('inviteQuery')<div class="kasku-form-error">{{ $message }}</div>@enderror
            </div>
            <div style="margin-bottom:12px">
                <label class="kasku-eyebrow" style="display:block;margin-bottom:6px">Role</label>
                <select wire:model="inviteRole" class="kasku-form-input">
                    <option value="member">Member (boleh catat)</option>
                    <option value="viewer">Viewer (hanya lihat)</option>
                </select>
                @error('inviteRole')<div class="kasku-form-error">{{ $message }}</div>@enderror
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" x-on:click="$flux.modal('invite-member').close()" class="kasku-btn">Batal</button>
                <button wire:click="sendInvite" class="kasku-btn kasku-btn--primary">Kirim undangan</button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete household confirm --}}
    <flux:modal name="delete-household">
        <div style="padding:16px">
            <div class="kasku-card-title" style="font-size:14px;margin-bottom:8px">Hapus household?</div>
            <p style="font-size:13px;color:var(--color-ink-2);margin-bottom:16px">Household yang masih punya akun atau transaksi tidak bisa dihapus. Pindahkan dulu data-nya atau hapus akun-nya.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" x-on:click="$flux.modal('delete-household').close()" class="kasku-btn">Batal</button>
                <button wire:click="deleteHousehold" class="kasku-btn" style="background:var(--color-neg);color:white">Hapus</button>
            </div>
        </div>
    </flux:modal>
</x-pages::settings.layout>
