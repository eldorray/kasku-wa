<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionService
{
    /**
     * Create an income/expense transaction in a household. The actor is the
     * recorded creator (`user_id`) — must be a member of the household.
     *
     * @param  array{account_id:int,category_id:int,label:string,amount:int,via:string,note:?string,merchant:?string,occurred_at:mixed,type?:string}  $data
     */
    public function create(Household $household, User $actor, array $data): Transaction
    {
        return DB::transaction(function () use ($household, $actor, $data) {
            $this->assertMember($household, $actor);
            $account = $this->lockAccount($household, (int) $data['account_id']);

            $type = $data['type'] ?? $this->inferType((int) $data['amount']);
            $this->assertNotTransfer($type);
            $signedAmount = $this->normalizeAmount((int) $data['amount'], $type);
            $this->assertCategoryMatchesType((int) $data['category_id'], $type);

            $tx = Transaction::create(array_merge($data, [
                'user_id' => $actor->id,
                'household_id' => $household->id,
                'amount' => $signedAmount,
                'type' => $type,
            ]));

            $account->increment('balance', $signedAmount);

            return $tx;
        });
    }

    /**
     * Update a non-transfer transaction. Permission: owner can edit any tx,
     * member can edit own tx, viewer cannot edit.
     */
    public function update(Transaction $tx, User $actor, array $data): Transaction
    {
        if ($tx->isTransfer()) {
            throw new InvalidArgumentException('Transfer transactions cannot be edited; delete and re-create.');
        }

        return DB::transaction(function () use ($tx, $actor, $data) {
            $household = $tx->household;
            $this->assertCanModify($household, $actor, $tx);

            $oldAccount = $this->lockAccount($household, (int) $tx->account_id);
            $oldAmount = (int) $tx->amount;

            $newAccountId = (int) ($data['account_id'] ?? $tx->account_id);
            $newType = $data['type'] ?? $tx->type ?? $this->inferType((int) ($data['amount'] ?? $oldAmount));
            $this->assertNotTransfer($newType);
            $newAmount = $this->normalizeAmount((int) ($data['amount'] ?? abs($oldAmount)), $newType);
            $this->assertCategoryMatchesType((int) ($data['category_id'] ?? $tx->category_id), $newType);

            $tx->fill(array_merge($data, ['amount' => $newAmount, 'type' => $newType]))->save();

            if ($newAccountId === (int) $oldAccount->id) {
                $delta = $newAmount - $oldAmount;
                if ($delta !== 0) {
                    $oldAccount->increment('balance', $delta);
                }
            } else {
                $newAccount = $this->lockAccount($household, $newAccountId);
                $oldAccount->increment('balance', -$oldAmount);
                $newAccount->increment('balance', $newAmount);
            }

            return $tx;
        });
    }

    public function delete(Transaction $tx, User $actor): void
    {
        DB::transaction(function () use ($tx, $actor) {
            $household = $tx->household;
            $this->assertCanModify($household, $actor, $tx);

            if ($tx->isTransfer() && $tx->transfer_pair_id) {
                $pair = Transaction::find($tx->transfer_pair_id);
                if ($pair) {
                    $pairAccount = $this->lockAccount($household, (int) $pair->account_id);
                    $pairAccount->increment('balance', -(int) $pair->amount);
                    $pair->delete();
                }
            }

            $account = $this->lockAccount($household, (int) $tx->account_id);
            $account->increment('balance', -(int) $tx->amount);
            $tx->delete();
        });
    }

    /**
     * @param  iterable<Transaction>  $txs
     */
    public function deleteMany(iterable $txs, User $actor): int
    {
        return DB::transaction(function () use ($txs, $actor) {
            $deltaByAccount = [];
            $ids = [];
            $pairIds = [];

            foreach ($txs as $tx) {
                $this->assertCanModify($tx->household, $actor, $tx);
                $deltaByAccount[$tx->account_id] = ($deltaByAccount[$tx->account_id] ?? 0) - (int) $tx->amount;
                $ids[] = $tx->id;
                if ($tx->isTransfer() && $tx->transfer_pair_id) {
                    $pairIds[] = $tx->transfer_pair_id;
                }
            }

            if (! empty($pairIds)) {
                $pairs = Transaction::whereIn('id', $pairIds)->whereNotIn('id', $ids)->get();
                foreach ($pairs as $pair) {
                    $deltaByAccount[$pair->account_id] = ($deltaByAccount[$pair->account_id] ?? 0) - (int) $pair->amount;
                    $ids[] = $pair->id;
                }
            }

            if (empty($ids)) {
                return 0;
            }

            foreach ($deltaByAccount as $accountId => $delta) {
                if ($delta !== 0) {
                    Account::whereKey($accountId)->lockForUpdate()->first()?->increment('balance', $delta);
                }
            }

            Transaction::whereIn('id', $ids)->delete();

            return count($ids);
        });
    }

    /**
     * Atomic transfer between two accounts in the SAME household.
     *
     * @return array{from:Transaction, to:Transaction}
     */
    public function transfer(Household $household, User $actor, int $fromAccountId, int $toAccountId, int $amount, ?string $note = null, ?\DateTimeInterface $occurredAt = null): array
    {
        $this->assertMember($household, $actor);
        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Akun sumber dan tujuan tidak boleh sama.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah transfer harus lebih dari 0.');
        }

        return DB::transaction(function () use ($household, $actor, $fromAccountId, $toAccountId, $amount, $note, $occurredAt) {
            [$lockFirst, $lockSecond] = $fromAccountId < $toAccountId
                ? [$fromAccountId, $toAccountId]
                : [$toAccountId, $fromAccountId];
            $a = $this->lockAccount($household, $lockFirst);
            $b = $this->lockAccount($household, $lockSecond);
            $from = $fromAccountId === $a->id ? $a : $b;
            $to = $fromAccountId === $a->id ? $b : $a;

            $occurredAt ??= now();
            $cat = Category::firstOrCreate(
                ['slug' => 'transfer'],
                ['label' => 'Transfer', 'emoji' => '🔁', 'color' => '#525252', 'bg' => '#e5e5e5', 'type' => Category::TYPE_BOTH],
            );

            $fromTx = Transaction::create([
                'user_id' => $actor->id,
                'household_id' => $household->id,
                'account_id' => $from->id,
                'category_id' => $cat->id,
                'label' => 'Transfer ke '.$to->label,
                'amount' => -$amount,
                'type' => Transaction::TYPE_TRANSFER,
                'via' => 'manual',
                'note' => $note,
                'merchant' => null,
                'occurred_at' => $occurredAt,
            ]);

            $toTx = Transaction::create([
                'user_id' => $actor->id,
                'household_id' => $household->id,
                'account_id' => $to->id,
                'category_id' => $cat->id,
                'label' => 'Transfer dari '.$from->label,
                'amount' => $amount,
                'type' => Transaction::TYPE_TRANSFER,
                'transfer_pair_id' => $fromTx->id,
                'via' => 'manual',
                'note' => $note,
                'merchant' => null,
                'occurred_at' => $occurredAt,
            ]);

            $fromTx->update(['transfer_pair_id' => $toTx->id]);

            $from->increment('balance', -$amount);
            $to->increment('balance', $amount);

            return ['from' => $fromTx, 'to' => $toTx];
        });
    }

    private function lockAccount(Household $household, int $accountId): Account
    {
        $account = Account::whereKey($accountId)
            ->where('household_id', $household->id)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            throw new InvalidArgumentException('Akun tidak ditemukan atau bukan milik household ini.');
        }

        return $account;
    }

    private function assertMember(Household $household, User $user): void
    {
        if (! $household->hasMember($user)) {
            throw new InvalidArgumentException('Pengguna bukan anggota household ini.');
        }
        if ($household->roleOf($user) === Household::ROLE_VIEWER) {
            throw new InvalidArgumentException('Viewer tidak boleh mencatat transaksi.');
        }
    }

    private function assertCanModify(Household $household, User $actor, Transaction $tx): void
    {
        $role = $household->roleOf($actor);
        if ($role === null) {
            throw new InvalidArgumentException('Pengguna bukan anggota household ini.');
        }
        if ($role === Household::ROLE_VIEWER) {
            throw new InvalidArgumentException('Viewer tidak boleh mengubah transaksi.');
        }
        if ($role === Household::ROLE_OWNER) {
            return;
        }
        if ((int) $tx->user_id !== (int) $actor->id) {
            throw new InvalidArgumentException('Anda hanya boleh mengubah transaksi yang Anda catat sendiri.');
        }
    }

    private function inferType(int $amount): string
    {
        return $amount >= 0 ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE;
    }

    private function normalizeAmount(int $amount, string $type): int
    {
        $magnitude = abs($amount);
        if ($magnitude === 0) {
            throw new InvalidArgumentException('Jumlah transaksi harus lebih dari 0.');
        }

        return $type === Transaction::TYPE_EXPENSE ? -$magnitude : $magnitude;
    }

    private function assertNotTransfer(string $type): void
    {
        if ($type === Transaction::TYPE_TRANSFER) {
            throw new InvalidArgumentException('Gunakan transfer() untuk transaksi transfer antar akun.');
        }
    }

    private function assertCategoryMatchesType(int $categoryId, string $txType): void
    {
        $cat = Category::find($categoryId);
        if (! $cat) {
            throw new InvalidArgumentException('Kategori tidak ditemukan.');
        }
        if (! $cat->acceptsType($txType)) {
            throw new InvalidArgumentException("Kategori \"{$cat->label}\" tidak cocok dengan tipe transaksi {$txType}.");
        }
    }
}
