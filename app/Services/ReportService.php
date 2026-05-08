<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array{start:Carbon, end:Carbon, prev_start:Carbon, prev_end:Carbon, label:string}
     */
    public function resolvePeriod(string $tab, ?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();

        return match ($tab) {
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'prev_start' => $now->copy()->subWeek()->startOfWeek(),
                'prev_end' => $now->copy()->subWeek()->endOfWeek(),
                'label' => 'Minggu ini ('.$now->copy()->startOfWeek()->locale('id')->isoFormat('D MMM').' — '.$now->copy()->endOfWeek()->locale('id')->isoFormat('D MMM').')',
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'prev_start' => $now->copy()->subYear()->startOfYear(),
                'prev_end' => $now->copy()->subYear()->endOfYear(),
                'label' => 'Tahun '.$now->format('Y'),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'prev_start' => $now->copy()->subMonth()->startOfMonth(),
                'prev_end' => $now->copy()->subMonth()->endOfMonth(),
                'label' => $now->locale('id')->isoFormat('MMMM YYYY'),
            ],
        };
    }

    /**
     * @return array{donut: Collection, total: int}
     */
    public function categoryBreakdown(Household $household, CarbonInterface $start, CarbonInterface $end): array
    {
        $tx = $this->expensesIn($household, $start, $end)->with('category')->get();

        $byCat = $tx->groupBy('category_id')->map(fn ($g) => abs((int) $g->sum('amount')));
        $total = (int) $byCat->sum();

        $cum = 0;
        $donut = $byCat->sortDesc()->map(function ($v, $catId) use ($total, &$cum, $tx) {
            $pct = $total > 0 ? $v / $total : 0;
            $startDeg = $cum * 360;
            $cum += $pct;
            $endDeg = $cum * 360;

            return (object) [
                'category' => $tx->firstWhere('category_id', $catId)?->category,
                'pct' => $pct,
                'start' => $startDeg,
                'end' => $endDeg,
                'value' => $v,
            ];
        })->values();

        return ['donut' => $donut, 'total' => $total];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>, sub_label: string}
     */
    public function expenseSeries(Household $household, string $tab, CarbonInterface $start, CarbonInterface $end): array
    {
        $values = [];
        $labels = [];

        if ($tab === 'year') {
            for ($m = 0; $m < 12; $m++) {
                $cursor = $start->copy()->addMonths($m);
                $monthStart = $cursor->copy()->startOfMonth();
                $monthEnd = $cursor->copy()->endOfMonth();
                $sum = abs((int) $this->expensesIn($household, $monthStart, $monthEnd)->sum('amount'));
                $values[] = $sum;
                $labels[] = $cursor->locale('id')->isoFormat('MMM');
            }
            $sub = 'Rata-rata Rp'.number_format(array_sum($values) / max(count(array_filter($values)), 1), 0, ',', '.').'/bulan';
        } else {
            $days = $tab === 'week' ? 7 : (int) $start->diffInDays($end) + 1;
            $rows = $this->expensesIn($household, $start, $end)
                ->selectRaw('DATE(occurred_at) as d, SUM(amount) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->toArray();

            for ($i = 0; $i < $days; $i++) {
                $cursor = $start->copy()->addDays($i);
                $key = $cursor->format('Y-m-d');
                $sum = abs((int) ($rows[$key] ?? 0));
                $values[] = $sum;
                $labels[] = $cursor->format('d');
            }
            $nonZero = count(array_filter($values));
            $sub = $nonZero > 0
                ? 'Rata-rata Rp'.number_format(array_sum($values) / $nonZero, 0, ',', '.').'/hari aktif'
                : 'Belum ada transaksi di periode ini';
        }

        return ['labels' => $labels, 'values' => $values, 'sub_label' => $sub];
    }

    public function topMerchants(Household $household, CarbonInterface $start, CarbonInterface $end, int $limit = 5): Collection
    {
        $rows = $this->expensesIn($household, $start, $end)
            ->with('category')
            ->whereNotNull('merchant')
            ->where('merchant', '!=', '')
            ->get();

        return $rows->groupBy('merchant')
            ->map(function ($group, $merchant) {
                $sum = abs((int) $group->sum('amount'));
                $count = $group->count();
                $catId = $group->groupBy('category_id')->sortByDesc(fn ($g) => $g->count())->keys()->first();
                $category = $group->firstWhere('category_id', $catId)?->category;

                return (object) [
                    'merchant' => $merchant,
                    'count' => $count,
                    'total' => $sum,
                    'category' => $category,
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, array{ico:string,t:string,s:string,col:string}>
     */
    public function insights(Household $household, string $tab, CarbonInterface $start, CarbonInterface $end, CarbonInterface $prevStart, CarbonInterface $prevEnd): array
    {
        $insights = [];

        $curIncome = $this->totalIncome($household, $start, $end);
        $prevIncome = $this->totalIncome($household, $prevStart, $prevEnd);
        $curExpense = $this->totalExpense($household, $start, $end);
        $prevExpense = $this->totalExpense($household, $prevStart, $prevEnd);

        if ($prevExpense > 0) {
            $delta = $curExpense - $prevExpense;
            $pct = (int) round(abs($delta) / $prevExpense * 100);
            if ($pct >= 5) {
                $insights[] = $delta < 0
                    ? ['ico' => '📉', 't' => 'Pengeluaran turun '.$pct.'%', 's' => 'Dari Rp'.number_format($prevExpense, 0, ',', '.').' → Rp'.number_format($curExpense, 0, ',', '.').' vs periode lalu. Pertahankan!', 'col' => 'var(--color-pos)']
                    : ['ico' => '📈', 't' => 'Pengeluaran naik '.$pct.'%', 's' => 'Naik Rp'.number_format($delta, 0, ',', '.').' dari periode lalu. Cek kategori boros.', 'col' => 'var(--color-warn)'];
            }
        }

        if ($prevIncome > 0) {
            $delta = $curIncome - $prevIncome;
            $pct = (int) round(abs($delta) / $prevIncome * 100);
            if ($pct >= 5 && $delta > 0) {
                $insights[] = ['ico' => '💼', 't' => 'Pemasukan naik '.$pct.'%', 's' => 'Plus Rp'.number_format($delta, 0, ',', '.').' dari periode lalu.', 'col' => 'var(--color-pos)'];
            }
        }

        $tx = $this->expensesIn($household, $start, $end)->with('category')->get();
        if ($tx->isNotEmpty()) {
            $byCat = $tx->groupBy('category_id')->map(fn ($g) => abs((int) $g->sum('amount')))->sortDesc();
            $topCatId = $byCat->keys()->first();
            $topCatTotal = $byCat->first();
            $topCat = $tx->firstWhere('category_id', $topCatId)?->category;
            if ($topCat && $curExpense > 0) {
                $pct = (int) round($topCatTotal / $curExpense * 100);
                $insights[] = [
                    'ico' => $topCat->emoji,
                    't' => 'Top kategori: '.$topCat->label,
                    's' => $pct.'% dari total pengeluaran (Rp'.number_format($topCatTotal, 0, ',', '.').').',
                    'col' => 'var(--color-info)',
                ];
            }
        }

        if ($tab === 'month') {
            $period = $start->format('Y-m');
            $budgets = $household->budgets()->with('category')->where('period', $period)->get();
            $overCount = 0;
            $overTotal = 0;
            $overCat = null;
            foreach ($budgets as $b) {
                $spent = $this->categorySpent($household, (int) $b->category_id, $start, $end);
                if ($spent > $b->monthly_limit) {
                    $overCount++;
                    if (! $overCat || ($spent - $b->monthly_limit) > $overTotal) {
                        $overTotal = $spent - $b->monthly_limit;
                        $overCat = $b->category;
                    }
                }
            }
            if ($overCount > 0 && $overCat) {
                $insights[] = [
                    'ico' => '🚨',
                    't' => $overCount.' kategori over-budget',
                    's' => $overCat->label.' paling kelebihan: Rp'.number_format($overTotal, 0, ',', '.').'. Naikkan limit atau kurangi pengeluaran.',
                    'col' => 'var(--color-neg)',
                ];
            }
        }

        $top = $this->topMerchants($household, $start, $end, 1)->first();
        if ($top && $top->count >= 3) {
            $insights[] = [
                'ico' => '🏪',
                't' => $top->merchant.' '.$top->count.'×',
                's' => 'Total Rp'.number_format($top->total, 0, ',', '.').' di '.$top->merchant.' periode ini.',
                'col' => 'var(--color-warn)',
            ];
        }

        return $insights;
    }

    public function totalIncome(Household $household, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) $household->transactions()
            ->where('type', Transaction::TYPE_INCOME)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');
    }

    public function totalExpense(Household $household, CarbonInterface $start, CarbonInterface $end): int
    {
        $sum = (int) $household->transactions()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        return abs($sum);
    }

    public function categorySpent(Household $household, int $categoryId, CarbonInterface $start, CarbonInterface $end): int
    {
        $sum = (int) $household->transactions()
            ->where('category_id', $categoryId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        return abs($sum);
    }

    public function expensesIn(Household $household, CarbonInterface $start, CarbonInterface $end): HasMany
    {
        return $household->transactions()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$start, $end]);
    }

    public function incomesIn(Household $household, CarbonInterface $start, CarbonInterface $end): HasMany
    {
        return $household->transactions()
            ->where('type', Transaction::TYPE_INCOME)
            ->whereBetween('occurred_at', [$start, $end]);
    }
}
