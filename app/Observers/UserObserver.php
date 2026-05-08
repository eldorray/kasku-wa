<?php

namespace App\Observers;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    public function created(User $user): void
    {
        DB::transaction(function () use ($user) {
            $name = trim((string) $user->name) ?: ('User #'.$user->id);
            $household = Household::create([
                'name' => 'Personal — '.$name,
                'slug' => Household::makeUniqueSlug('personal-'.$name),
                'default_currency' => 'IDR',
                'created_by' => $user->id,
            ]);

            $household->members()->attach($user->id, [
                'role' => Household::ROLE_OWNER,
                'joined_at' => now(),
                'invited_by' => null,
            ]);

            $user->forceFill(['current_household_id' => $household->id])->saveQuietly();
        });
    }
}
