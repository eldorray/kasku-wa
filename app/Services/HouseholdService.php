<?php

namespace App\Services;

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HouseholdService
{
    public function create(User $owner, string $name): Household
    {
        return DB::transaction(function () use ($owner, $name) {
            $household = Household::create([
                'name' => $name,
                'default_currency' => 'IDR',
                'created_by' => $owner->id,
            ]);
            $household->members()->attach($owner->id, [
                'role' => Household::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            return $household;
        });
    }

    public function rename(Household $household, User $actor, string $name): Household
    {
        $this->assertOwner($household, $actor);
        $household->update(['name' => $name]);

        return $household;
    }

    public function delete(Household $household, User $actor): void
    {
        $this->assertOwner($household, $actor);
        if ($household->accounts()->exists() || $household->transactions()->exists()) {
            throw new InvalidArgumentException('Tidak bisa hapus household yang masih punya akun atau transaksi.');
        }
        $household->delete();
    }

    /**
     * Find a registered user by exact email or normalized phone.
     */
    public function findUserByEmailOrPhone(string $query): ?User
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        if (str_contains($q, '@')) {
            return User::whereRaw('LOWER(email) = ?', [mb_strtolower($q)])->first();
        }

        $normalized = User::normalizePhone($q);
        if (! $normalized) {
            return null;
        }

        return User::where('phone_normalized', $normalized)->first();
    }

    /**
     * Invite a registered user to a household. Owner-only.
     */
    public function inviteUser(Household $household, User $actor, User $target, string $role = Household::ROLE_MEMBER): HouseholdInvite
    {
        $this->assertOwner($household, $actor);
        if (! in_array($role, [Household::ROLE_MEMBER, Household::ROLE_VIEWER], true)) {
            throw new InvalidArgumentException('Role undangan harus member atau viewer.');
        }
        if ($actor->id === $target->id) {
            throw new InvalidArgumentException('Tidak bisa mengundang diri sendiri.');
        }
        if ($household->hasMember($target)) {
            throw new InvalidArgumentException("{$target->name} sudah jadi anggota household ini.");
        }

        $existing = HouseholdInvite::where('household_id', $household->id)
            ->where('invited_user_id', $target->id)
            ->pending()
            ->first();
        if ($existing) {
            throw new InvalidArgumentException("{$target->name} sudah diundang dan masih menunggu konfirmasi.");
        }

        return HouseholdInvite::create([
            'household_id' => $household->id,
            'invited_by' => $actor->id,
            'invited_user_id' => $target->id,
            'invited_email' => $target->email,
            'invited_phone' => $target->phone_normalized,
            'role' => $role,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function cancelInvite(HouseholdInvite $invite, User $actor): void
    {
        $this->assertOwner($invite->household, $actor);
        if (! $invite->isPending()) {
            throw new InvalidArgumentException('Undangan ini sudah tidak pending.');
        }
        $invite->delete();
    }

    /**
     * Accept an invite. Target must match the auth user.
     * Does NOT auto-switch the user's current household — they keep using the
     * one they had, and can switch manually from the households page.
     */
    public function acceptInvite(HouseholdInvite $invite, User $user): Household
    {
        return DB::transaction(function () use ($invite, $user) {
            $invite = HouseholdInvite::whereKey($invite->id)->lockForUpdate()->first();
            if (! $invite) {
                throw new InvalidArgumentException('Undangan tidak ditemukan.');
            }
            if ((int) $invite->invited_user_id !== (int) $user->id) {
                throw new InvalidArgumentException('Undangan ini bukan untuk Anda.');
            }
            if (! $invite->isPending()) {
                throw new InvalidArgumentException('Undangan sudah tidak pending.');
            }

            $household = $invite->household;
            if (! $household->hasMember($user)) {
                $household->members()->attach($user->id, [
                    'role' => $invite->role,
                    'joined_at' => now(),
                    'invited_by' => $invite->invited_by,
                ]);
            }

            $invite->update(['accepted_at' => now(), 'accepted_by_user_id' => $user->id]);

            return $household;
        });
    }

    public function rejectInvite(HouseholdInvite $invite, User $user): void
    {
        if ((int) $invite->invited_user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Undangan ini bukan untuk Anda.');
        }
        if (! $invite->isPending()) {
            throw new InvalidArgumentException('Undangan sudah tidak pending.');
        }
        $invite->update(['rejected_at' => now()]);
    }

    /**
     * Pending invites where the user is the target (inbox).
     */
    public function pendingInvitesFor(User $user)
    {
        return HouseholdInvite::with(['household', 'inviter'])
            ->where('invited_user_id', $user->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pending invites a household has sent (outbox), for owner UI.
     */
    public function pendingInvitesFrom(Household $household)
    {
        return HouseholdInvite::with(['invitee', 'inviter'])
            ->where('household_id', $household->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get();
    }

    public function removeMember(Household $household, User $actor, User $target): void
    {
        $this->assertOwner($household, $actor);
        if ($household->isOwner($target)) {
            throw new InvalidArgumentException('Owner tidak bisa dikeluarkan; transfer ownership dulu.');
        }
        $household->members()->detach($target->id);

        if ((int) $target->current_household_id === (int) $household->id) {
            $next = $target->households()->orderBy('households.id')->first();
            $target->forceFill(['current_household_id' => $next?->id])->save();
        }
    }

    public function changeRole(Household $household, User $actor, User $target, string $role): void
    {
        $this->assertOwner($household, $actor);
        if (! in_array($role, [Household::ROLE_OWNER, Household::ROLE_MEMBER, Household::ROLE_VIEWER], true)) {
            throw new InvalidArgumentException('Role tidak valid.');
        }
        $household->members()->updateExistingPivot($target->id, ['role' => $role]);
    }

    public function switchTo(User $user, Household $household): void
    {
        if (! $household->hasMember($user)) {
            throw new InvalidArgumentException('Anda bukan anggota household ini.');
        }
        $user->forceFill(['current_household_id' => $household->id])->save();
    }

    private function assertOwner(Household $household, User $actor): void
    {
        if (! $household->isOwner($actor)) {
            throw new InvalidArgumentException('Hanya owner yang boleh melakukan aksi ini.');
        }
    }
}
