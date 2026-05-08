<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GoalService
{
    /**
     * @param  array{label:string,target:int,current?:int,due_label:?string,color:string,account_id?:?int}  $data
     */
    public function create(Household $household, User $actor, array $data): Goal
    {
        $this->assertCanEdit($household, $actor);
        $this->assertOwnedAccountIfSet($household, $data['account_id'] ?? null);
        if ((int) $data['target'] <= 0) {
            throw new InvalidArgumentException('Target goal harus lebih dari 0.');
        }

        return $household->goals()->create([
            'user_id' => $actor->id,
            'account_id' => $data['account_id'] ?? null,
            'label' => $data['label'],
            'target' => (int) $data['target'],
            'current' => max(0, min((int) ($data['current'] ?? 0), (int) $data['target'])),
            'due_label' => $data['due_label'] ?: null,
            'color' => $data['color'],
        ]);
    }

    public function update(Goal $goal, User $actor, array $data): Goal
    {
        $this->assertCanEdit($goal->household, $actor);
        $this->assertOwnedAccountIfSet($goal->household, $data['account_id'] ?? $goal->account_id);
        if (isset($data['target']) && (int) $data['target'] <= 0) {
            throw new InvalidArgumentException('Target goal harus lebih dari 0.');
        }

        $target = (int) ($data['target'] ?? $goal->target);
        $current = isset($data['current'])
            ? max(0, min((int) $data['current'], $target))
            : min((int) $goal->current, $target);

        $goal->fill(array_merge($data, [
            'target' => $target,
            'current' => $current,
        ]))->save();

        $this->refreshCompletion($goal);

        return $goal;
    }

    public function delete(Goal $goal, User $actor): void
    {
        $this->assertCanEdit($goal->household, $actor);
        $goal->delete();
    }

    public function addProgress(Goal $goal, User $actor, int $amount): Goal
    {
        $this->assertCanEdit($goal->household, $actor);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah harus lebih dari 0.');
        }
        $goal->current = min((int) $goal->target, (int) $goal->current + $amount);
        $goal->save();
        $this->refreshCompletion($goal);

        return $goal;
    }

    public function reduceProgress(Goal $goal, User $actor, int $amount): Goal
    {
        $this->assertCanEdit($goal->household, $actor);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah harus lebih dari 0.');
        }
        $goal->current = max(0, (int) $goal->current - $amount);
        $goal->completed_at = null;
        $goal->save();

        return $goal;
    }

    public function markComplete(Goal $goal, User $actor): Goal
    {
        $this->assertCanEdit($goal->household, $actor);
        $goal->current = (int) $goal->target;
        $goal->completed_at = now();
        $goal->save();

        return $goal;
    }

    public function syncFromAccount(Goal $goal): Goal
    {
        if (! $goal->account_id) {
            return $goal;
        }
        DB::transaction(function () use ($goal) {
            $goal->refresh();
            if (! $goal->account) {
                return;
            }
            $goal->current = max(0, min((int) $goal->target, (int) $goal->account->balance));
            $goal->save();
            $this->refreshCompletion($goal);
        });

        return $goal->fresh();
    }

    public function syncAllForHousehold(Household $household): int
    {
        $count = 0;
        foreach ($household->goals()->whereNotNull('account_id')->get() as $goal) {
            $this->syncFromAccount($goal);
            $count++;
        }

        return $count;
    }

    private function refreshCompletion(Goal $goal): void
    {
        if ((int) $goal->current >= (int) $goal->target && $goal->target > 0) {
            if (! $goal->completed_at) {
                $goal->completed_at = now();
                $goal->save();
            }
        } elseif ($goal->completed_at) {
            $goal->completed_at = null;
            $goal->save();
        }
    }

    private function assertCanEdit(Household $household, User $actor): void
    {
        if (! $household->canEdit($actor)) {
            throw new InvalidArgumentException('Anda tidak punya izin mengubah goal di household ini.');
        }
    }

    private function assertOwnedAccountIfSet(Household $household, ?int $accountId): void
    {
        if ($accountId === null) {
            return;
        }
        $owns = $household->accounts()->whereKey($accountId)->exists();
        if (! $owns) {
            throw new InvalidArgumentException('Akun tidak ditemukan di household ini.');
        }
    }
}
