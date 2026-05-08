<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Household extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    protected static function booted(): void
    {
        static::creating(function (self $h) {
            if (empty($h->slug)) {
                $h->slug = self::makeUniqueSlug($h->name);
            }
        });
    }

    public static function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'household';
        $slug = $base;
        $i = 2;
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return Str::limit($slug, 60, '');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->withPivot('role', 'joined_at', 'invited_by')
            ->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    public function hasMember(User|int $user): bool
    {
        $id = $user instanceof User ? $user->id : (int) $user;

        return $this->members()->where('users.id', $id)->exists();
    }

    public function roleOf(User|int $user): ?string
    {
        $id = $user instanceof User ? $user->id : (int) $user;
        $member = $this->members()->where('users.id', $id)->first();

        return $member?->pivot->role;
    }

    public function isOwner(User|int $user): bool
    {
        return $this->roleOf($user) === self::ROLE_OWNER;
    }

    public function canEdit(User|int $user): bool
    {
        $role = $this->roleOf($user);

        return in_array($role, [self::ROLE_OWNER, self::ROLE_MEMBER], true);
    }
}
