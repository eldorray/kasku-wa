<?php

namespace App\Services\Whatsapp;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\ReportService;
use App\Support\Money;
use Carbon\Carbon;

class CommandHandler
{
    public function __construct(
        private readonly ReportService $reports = new ReportService(),
        private readonly BudgetService $budgets = new BudgetService(),
    ) {}

    public function isCommand(string $message): bool
    {
        return str_starts_with(trim($message), '/');
    }

    public function handle(User $user, string $message): string
    {
        $household = $user->resolveHousehold();
        if (! $household) {
            return "Akun belum terhubung household. Buka aplikasi → Settings → Household.";
        }

        $cmd = strtolower(trim(explode(' ', trim($message), 2)[0] ?? ''));
        $args = trim(explode(' ', trim($message), 2)[1] ?? '');

        return match ($cmd) {
            '/help', '/bantuan' => $this->help(),
            '/saldo', '/balance' => $this->balance($household),
            '/laporan', '/report' => $this->report($household),
            '/budget' => $this->budget($household),
            '/buatakun', '/createaccount' => $this->createAccount($household, $user, $args),
            default => "Perintah `{$cmd}` belum dikenali. Ketik /help untuk daftar perintah.",
        };
    }

    private function help(): string
    {
        return "Perintah Kasku Bot:\n\n"
            ."• /saldo — total saldo household aktif\n"
            ."• /laporan — ringkasan bulan ini\n"
            ."• /budget — status semua budget\n"
            ."• /buatakun Nama Akun — buat akun/dompet baru\n"
            ."• /help — pesan ini\n\n"
            ."Atau langsung ketik transaksi:\n"
            ."`kopi tuku 28rb akun BCA`\n"
            ."`gaji masuk 5jt akun Cash`\n"
            ."`/expense bensin 50rb`";
    }

    private function createAccount(Household $household, User $user, string $args): string
    {
        $label = trim($args);

        if ($label === '') {
            return "Format buat akun/dompet:\n`/buatakun Nama Akun`\n\nContoh:\n`/buatakun Cash`";
        }

        $existing = $household->accounts()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->first();

        if ($existing) {
            return "Akun *{$existing->label}* sudah ada di {$household->name}.\n\nKetik transaksi dengan format:\n`gaji masuk 5jt akun {$existing->label}`";
        }

        $account = Account::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'label' => ucwords(mb_strtolower($label)),
            'type' => 'Wallet',
            'last_four' => null,
            'balance' => 0,
            'color' => '#525252',
        ]);

        return "Akun berhasil dibuat di {$household->name} ✅\n\n"
            ."Nama: *{$account->label}*\n"
            ."Saldo awal: ".Money::fmt(0)."\n\n"
            ."Sekarang kamu bisa catat pemasukan/pengeluaran, contoh:\n"
            ."`gaji masuk 5jt akun {$account->label}`";
    }

    private function balance(Household $household): string
    {
        $accounts = $household->accounts()->get();
        $total = (int) $accounts->sum('balance');
        $lines = ["💰 {$household->name}", "Total saldo: ".Money::fmt($total), ''];
        foreach ($accounts as $a) {
            $lines[] = "• {$a->label}: ".Money::fmt((int) $a->balance);
        }

        return implode("\n", $lines);
    }

    private function report(Household $household): string
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $income = $this->reports->totalIncome($household, $start, $end);
        $expense = $this->reports->totalExpense($household, $start, $end);
        $count = (int) $household->transactions()
            ->where('type', '!=', Transaction::TYPE_TRANSFER)
            ->whereBetween('occurred_at', [$start, $end])
            ->count();

        return "📊 {$household->name} — ".$start->locale('id')->isoFormat('MMMM YYYY')."\n\n"
            ."Pemasukan: ".Money::fmt($income)."\n"
            ."Pengeluaran: ".Money::fmt($expense)."\n"
            ."Net: ".Money::fmt($income - $expense)."\n"
            ."Total transaksi: {$count}";
    }

    private function budget(Household $household): string
    {
        $period = Carbon::now()->format('Y-m');
        $budgets = $household->budgets()->with('category')->where('period', $period)->get();

        if ($budgets->isEmpty()) {
            return "Belum ada budget untuk bulan ini di {$household->name}. Atur di dashboard → Kategori & Budget.";
        }

        $lines = ["🎯 Budget {$household->name} — ".Carbon::parse($period.'-01')->locale('id')->isoFormat('MMMM YYYY'), ''];
        foreach ($budgets as $b) {
            $spent = $this->budgets->spent($household, (int) $b->category_id, $period);
            $pct = $b->monthly_limit > 0 ? (int) round($spent / $b->monthly_limit * 100) : 0;
            $emoji = $pct >= 100 ? '🔴' : ($pct > 80 ? '🟡' : '🟢');
            $lines[] = "{$emoji} {$b->category->emoji} {$b->category->label}: ".Money::fmtShort($spent)." / ".Money::fmtShort((int) $b->monthly_limit)." ({$pct}%)";
        }

        return implode("\n", $lines);
    }
}
