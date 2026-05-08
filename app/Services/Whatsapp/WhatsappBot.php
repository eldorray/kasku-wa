<?php

namespace App\Services\Whatsapp;

use App\Models\Account;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappBot
{
    public function __construct(
        private readonly MessageParser $parser,
        private readonly CommandHandler $commands,
        private readonly FonnteClient $fonnte,
        private readonly TransactionService $txService,
    ) {}

    public static function normalizePhone(string $phone): string
    {
        return User::normalizePhone($phone) ?? '';
    }

    public function findUserByPhone(string $sender): ?User
    {
        $normalized = User::normalizePhone($sender);
        if (! $normalized) {
            return null;
        }

        return User::where('phone_normalized', $normalized)->first();
    }

    /**
     * @return array{user_id:?int, reply:string, transaction_id:?int}
     */
    public function handleIncoming(string $sender, string $body, ?Carbon $occurredAt = null): array
    {
        $occurredAt ??= Carbon::now();
        $user = $this->findUserByPhone($sender);

        if (! $user) {
            return [
                'user_id' => null,
                'reply' => "Nomor {$sender} belum terdaftar di Kasku. Daftar dulu di aplikasi & isi nomor WhatsApp di Pengaturan.",
                'transaction_id' => null,
            ];
        }

        $household = $user->resolveHousehold();
        if (! $household) {
            return [
                'user_id' => $user->id,
                'reply' => "Akun Anda belum terhubung ke household. Buka aplikasi → Settings → Household.",
                'transaction_id' => null,
            ];
        }
        // Bind for downstream services that read currentHousehold().
        app()->instance('current_household', $household);

        return DB::transaction(function () use ($user, $household, $body, $occurredAt) {
            $conversation = $this->ensureBotConversation($user);
            $this->logIncoming($user, $conversation, $body, $occurredAt);

            $txId = null;
            $kind = 'text';
            $meta = [];
            $reply = '';

            if ($this->commands->isCommand($body)) {
                $reply = $this->commands->handle($user, $body);
                $kind = 'report';
                $meta = ['command' => true];
            } else {
                $intent = $this->parser->parse($body);
                if ($intent === null) {
                    $reply = "Hmm, aku belum paham 🤔\n\nCoba format seperti:\n`kopi 28rb` atau `gojek 42rb`\n\nKetik /help untuk daftar perintah.";
                    $meta = ['parse_failed' => true];
                } else {
                    $account = $this->resolveAccountFromIntent($household, $intent);

                    if (! $account) {
                        $reply = $this->formatAccountRequiredReply($household);
                        $meta = ['account_required' => true];
                    } else {
                        try {
                            $tx = $this->createTransactionFromIntent($household, $user, $intent, $account, $occurredAt);
                            $txId = $tx->id;
                            $reply = $this->formatReceiptReply($tx, $household);
                            $kind = 'receipt';
                            $meta = ['transaction_id' => $tx->id];
                        } catch (Throwable $e) {
                            Log::warning('[WA] tx create failed', ['err' => $e->getMessage(), 'user_id' => $user->id]);
                            $reply = "Gagal mencatat transaksi: ".$e->getMessage();
                            $meta = ['error' => true];
                        }
                    }
                }
            }

            $this->logOutgoing($user, $conversation, $reply, $kind, $meta, $txId);
            $this->fonnte->send($user->phone, $reply);

            return [
                'user_id' => $user->id,
                'reply' => $reply,
                'transaction_id' => $txId,
            ];
        });
    }

    private function ensureBotConversation(User $user): Conversation
    {
        return Conversation::firstOrCreate(
            ['user_id' => $user->id, 'slug' => config('whatsapp.bot.conversation_slug', 'kasku')],
            [
                'name' => config('whatsapp.bot.name', 'Kasku Bot'),
                'online' => true,
                'pinned' => true,
            ],
        );
    }

    private function logIncoming(User $user, Conversation $conv, string $body, Carbon $at): void
    {
        Message::create([
            'user_id' => $user->id,
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'kind' => 'text',
            'body' => $body,
            'occurred_at' => $at,
        ]);

        $conv->update([
            'last_message' => mb_substr($body, 0, 120),
            'last_at_label' => $at->format('H:i'),
            'unread' => $conv->unread + 1,
        ]);
    }

    private function logOutgoing(User $user, Conversation $conv, string $body, string $kind, array $meta, ?int $txId): void
    {
        Message::create([
            'user_id' => $user->id,
            'conversation_id' => $conv->id,
            'transaction_id' => $txId,
            'direction' => 'out',
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta,
            'occurred_at' => Carbon::now(),
        ]);

        $conv->update([
            'last_message' => mb_substr(strip_tags($body), 0, 120),
            'last_at_label' => Carbon::now()->format('H:i'),
        ]);
    }

    private function resolveAccountFromIntent(\App\Models\Household $household, array $intent): ?Account
    {
        $accounts = $household->accounts()->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $accountLabel = $intent['account_label'] ?? null;

        if ($accountLabel) {
            $normalizedLabel = mb_strtolower(trim($accountLabel));

            return $accounts->first(
                fn (Account $account) => mb_strtolower($account->label) === $normalizedLabel
            );
        }

        if ($accounts->count() === 1) {
            return $accounts->first();
        }

        return null;
    }

    private function formatAccountRequiredReply(\App\Models\Household $household): string
    {
        $accounts = $household->accounts()->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            return "Transaksi belum dicatat ⚠️\n\n"
                ."Kamu belum punya akun/dompet untuk menampung pemasukan atau pengeluaran.\n\n"
                ."Buat dulu dengan mengetik:\n"
                ."`/buatakun Cash`\n\n"
                ."Setelah itu catat transaksi, contoh:\n"
                ."`gaji masuk 5jt akun Cash`";
        }

        $lines = [
            "Transaksi belum dicatat ⚠️",
            '',
            'Mau dimasukkan ke akun/dompet mana?',
            '',
        ];

        foreach ($accounts as $account) {
            $lines[] = "• {$account->label}";
        }

        $lines[] = '';
        $lines[] = 'Ketik ulang transaksi dengan menambahkan nama akun/dompet, contoh:';
        $lines[] = '`gaji masuk 5jt akun '.$accounts->first()->label.'`';

        return implode("\n", $lines);
    }

    private function createTransactionFromIntent(\App\Models\Household $household, User $user, array $intent, Account $account, Carbon $at): Transaction
    {
        $type = $intent['type'] === 'income' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE;
        $cat = $this->resolveCategoryForType($intent['category'], $type);
        $label = $intent['merchant'] ?? Money::fmt($intent['amount']);

        return $this->txService->create($household, $user, [
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'label' => $label,
            'amount' => abs((int) $intent['amount']),
            'type' => $type,
            'via' => 'wa',
            'note' => $intent['note'],
            'merchant' => $intent['merchant'],
            'occurred_at' => $at,
        ]);
    }

    private function resolveCategoryForType(string $slug, string $type): Category
    {
        $cat = Category::where('slug', $slug)->first();
        if ($cat && $cat->acceptsType($type)) {
            return $cat;
        }

        // Fallback: pick generic category for the type.
        $fallbackSlug = $type === Transaction::TYPE_INCOME ? 'other-income' : 'other';

        return Category::firstOrCreate(
            ['slug' => $fallbackSlug],
            [
                'label' => $type === Transaction::TYPE_INCOME ? 'Pemasukan Lain' : 'Lain-lain',
                'emoji' => $type === Transaction::TYPE_INCOME ? '💵' : '🗂️',
                'color' => '#525252',
                'bg' => '#e5e5e5',
                'type' => $type,
            ],
        );
    }

    private function formatReceiptReply(Transaction $tx, \App\Models\Household $household): string
    {
        $type = $tx->amount > 0 ? 'Pemasukan' : 'Pengeluaran';
        $cat = $tx->category;
        $acc = $tx->account;
        $amount = Money::fmt(abs((int) $tx->amount));

        return "Tercatat ✅\n\n"
            ."Household: {$household->name}\n"
            ."Tipe: {$cat->emoji} {$type}\n"
            ."Kategori: {$cat->label}\n"
            ."Akun: {$acc->label}\n"
            .($tx->merchant ? "Merchant: {$tx->merchant}\n" : '')
            ."Total: *{$amount}*\n\n"
            ."Ketik /laporan untuk ringkasan, /budget untuk cek limit.";
    }
}
