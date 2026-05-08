<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class BudgetService
{
    public function setLimit(Household $household, User $actor, int $categoryId, string $period, int $limit): Budget
    {
        $this->assertCanEdit($household, $actor);
        $this->assertPeriod($period);
        if ($limit < 0) {
            throw new InvalidArgumentException('Budget tidak boleh negatif.');
        }

        return Budget::updateOrCreate(
            [
                'household_id' => $household->id,
                'category_id' => $categoryId,
                'period' => $period,
            ],
            [
                'user_id' => $actor->id,
                'monthly_limit' => $limit,
            ],
        );
    }

    public function delete(Budget $budget, User $actor): void
    {
        $this->assertCanEdit($budget->household, $actor);
        $budget->delete();
    }

    /**
     * Spent total in a category for the given household & period (expense-type only).
     */
    public function spent(Household $household, int $categoryId, string $period): int
    {
        $this->assertPeriod($period);
        $start = Carbon::parse($period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $sum = (int) $household->transactions()
            ->where('category_id', $categoryId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        return abs($sum);
    }

    /**
     * @return array{copied:int, skipped:int}
     */
    public function cloneFromPreviousMonth(Household $household, User $actor, string $targetPeriod): array
    {
        $this->assertCanEdit($household, $actor);
        $this->assertPeriod($targetPeriod);
        $previous = Carbon::parse($targetPeriod.'-01')->subMonth()->format('Y-m');
        $previousBudgets = $household->budgets()->where('period', $previous)->get();
        $existing = $household->budgets()->where('period', $targetPeriod)->pluck('category_id')->all();

        $copied = 0;
        $skipped = 0;
        foreach ($previousBudgets as $b) {
            if (in_array($b->category_id, $existing, true)) {
                $skipped++;
                continue;
            }
            Budget::create([
                'household_id' => $household->id,
                'user_id' => $actor->id,
                'category_id' => $b->category_id,
                'period' => $targetPeriod,
                'monthly_limit' => $b->monthly_limit,
            ]);
            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    private function assertCanEdit(Household $household, User $actor): void
    {
        if (! $household->canEdit($actor)) {
            throw new InvalidArgumentException('Anda tidak punya izin mengubah budget di household ini.');
        }
    }

    private function assertPeriod(string $period): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException("Format periode budget harus YYYY-MM, dapat: {$period}");
        }
    }
}
