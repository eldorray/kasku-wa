<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountService
{
    /**
     * @param  array{label:string,type:string,last_four:?string,balance:int,color:string}  $data
     */
    public function create(Household $household, User $actor, array $data): Account
    {
        $this->assertCanEdit($household, $actor);

        return DB::transaction(function () use ($household, $actor, $data) {
            $balance = max(0, (int) $data['balance']);

            $account = Account::create([
                'household_id' => $household->id,
                'user_id' => $actor->id,
                'label' => $data['label'],
                'type' => $data['type'],
                'last_four' => $data['last_four'] ?: null,
                'balance' => 0,
                'color' => $data['color'],
            ]);

            if ($balance > 0) {
                $category = Category::firstOrCreate(
                    ['slug' => 'initial-balance'],
                    [
                        'label' => 'Saldo Awal',
                        'emoji' => '💰',
                        'color' => '#1f8a5b',
                        'bg' => '#dcfce7',
                        'type' => Category::TYPE_INCOME,
                    ]
                );

                Transaction::create([
                    'user_id' => $actor->id,
                    'household_id' => $household->id,
                    'account_id' => $account->id,
                    'category_id' => $category->id,
                    'label' => 'Saldo awal '.$account->label,
                    'amount' => $balance,
                    'type' => Transaction::TYPE_INCOME,
                    'via' => 'manual',
                    'note' => 'Otomatis dibuat saat akun/dompet ditambahkan.',
                    'merchant' => null,
                    'occurred_at' => now(),
                ]);

                $account->increment('balance', $balance);
            }

            return $account;
        });
    }

    /**
     * @param  array{label:string,type:string,last_four:?string,color:string}  $data
     */
    public function update(Account $account, User $actor, array $data): Account
    {
        $this->assertCanEdit($account->household, $actor);

        $account->fill([
            'label' => $data['label'],
            'type' => $data['type'],
            'last_four' => $data['last_four'] ?: null,
            'color' => $data['color'],
        ])->save();

        return $account;
    }

    public function adjustBalance(Account $account, User $actor, int $targetBalance, TransactionService $txService): ?Transaction
    {
        $delta = $targetBalance - (int) $account->balance;
        if ($delta === 0) {
            return null;
        }

        $cat = Category::firstOrCreate(
            ['slug' => 'adjustment'],
            ['label' => 'Penyesuaian', 'emoji' => '⚖️', 'color' => '#525252', 'bg' => '#e5e5e5', 'type' => Category::TYPE_BOTH],
        );

        return $txService->create($account->household, $actor, [
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'label' => 'Penyesuaian saldo '.$account->label,
            'amount' => $delta,
            'type' => $delta > 0 ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE,
            'via' => 'manual',
            'note' => 'Rekonsiliasi saldo manual.',
            'merchant' => null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{ok:bool, error?:string, tx_count?:int, goal_count?:int}
     */
    public function delete(Account $account, User $actor): array
    {
        $this->assertCanEdit($account->household, $actor);

        $txCount = (int) $account->transactions()->count();
        if ($txCount > 0) {
            return ['ok' => false, 'error' => 'has_transactions', 'tx_count' => $txCount];
        }
        $goalCount = (int) $account->goals()->count();
        if ($goalCount > 0) {
            return ['ok' => false, 'error' => 'has_goals', 'goal_count' => $goalCount];
        }

        $account->delete();

        return ['ok' => true];
    }

    /**
     * Compute total balance per day for the last N days, scoped to the household.
     *
     * @return array<int, array{date:string, balance:int}>
     */
    public function historicalTotalBalance(Household $household, int $days = 30): array
    {
        $current = (int) $household->accounts()->sum('balance');
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $rows = $household->transactions()
            ->where('occurred_at', '>=', $start->copy()->startOfDay())
            ->where('occurred_at', '<=', $end->copy()->endOfDay())
            ->selectRaw('DATE(occurred_at) as d, SUM(amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd')
            ->toArray();

        $series = [];
        $cursor = $end->copy();
        $running = $current;
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $series[] = ['date' => $key, 'balance' => $running];
            $running -= (int) ($rows[$key] ?? 0);
            $cursor = $cursor->subDay();
        }

        return array_reverse($series);
    }

    private function assertCanEdit(Household $household, User $actor): void
    {
        if (! $household->canEdit($actor)) {
            throw new InvalidArgumentException('Anda tidak punya izin mengubah akun di household ini.');
        }
    }
}
