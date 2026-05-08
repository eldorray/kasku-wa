<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Goal;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class KaskuSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'rama@kasku.test'],
            [
                'name' => 'Rama Adriansyah',
                'phone' => '+6281287314422',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );
        if (! $user->phone) {
            $user->update(['phone' => '+6281287314422']);
        }

        $household = $user->households()->first()
            ?? Household::create(['name' => 'Personal — '.$user->name, 'created_by' => $user->id]);
        if (! $user->households()->where('households.id', $household->id)->exists()) {
            $user->households()->attach($household->id, ['role' => Household::ROLE_OWNER]);
        }
        if (! $user->current_household_id) {
            $user->forceFill(['current_household_id' => $household->id])->save();
        }
        $householdId = $household->id;

        $categoriesData = [
            ['slug' => 'food',      'label' => 'Makan & Minum',      'emoji' => '🍚', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
            ['slug' => 'transport', 'label' => 'Transportasi',        'emoji' => '🛵', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
            ['slug' => 'work',      'label' => 'Pendapatan Klien',    'emoji' => '💼', 'color' => '#1f8a5b', 'bg' => '#dcfce7'],
            ['slug' => 'shop',      'label' => 'Belanja',             'emoji' => '🛍️', 'color' => '#ec4899', 'bg' => '#fce7f3'],
            ['slug' => 'bill',      'label' => 'Tagihan',             'emoji' => '🧾', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
            ['slug' => 'health',    'label' => 'Kesehatan',           'emoji' => '🩺', 'color' => '#14b8a6', 'bg' => '#ccfbf1'],
            ['slug' => 'fun',       'label' => 'Hiburan',             'emoji' => '🎬', 'color' => '#f43f5e', 'bg' => '#ffe4e6'],
            ['slug' => 'save',      'label' => 'Tabungan',            'emoji' => '🏦', 'color' => '#0ea5e9', 'bg' => '#e0f2fe'],
            ['slug' => 'other',     'label' => 'Lainnya',             'emoji' => '✨', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
        ];
        $categoryIds = [];
        foreach ($categoriesData as $c) {
            $cat = Category::updateOrCreate(['slug' => $c['slug']], $c);
            $categoryIds[$c['slug']] = $cat->id;
        }

        $accountsData = [
            ['slug' => 'bca',   'label' => 'BCA Tahapan', 'type' => 'Bank',     'last_four' => '4521', 'balance' => 18750000, 'color' => '#1d4ed8'],
            ['slug' => 'jago',  'label' => 'Bank Jago',   'type' => 'Bank',     'last_four' => '8812', 'balance' => 4280000,  'color' => '#f97316'],
            ['slug' => 'gopay', 'label' => 'GoPay',       'type' => 'E-wallet', 'last_four' => '0810', 'balance' => 685000,   'color' => '#10b981'],
            ['slug' => 'ovo',   'label' => 'OVO',         'type' => 'E-wallet', 'last_four' => '0810', 'balance' => 142000,   'color' => '#7c3aed'],
            ['slug' => 'cash',  'label' => 'Tunai',       'type' => 'Cash',     'last_four' => null,   'balance' => 320000,   'color' => '#525252'],
        ];
        $accountIds = [];
        foreach ($accountsData as $a) {
            $slug = $a['slug'];
            unset($a['slug']);
            $acc = Account::updateOrCreate(
                ['user_id' => $user->id, 'label' => $a['label']],
                array_merge($a, ['user_id' => $user->id, 'household_id' => $householdId]),
            );
            $accountIds[$slug] = $acc->id;
        }

        $txData = [
            ['when' => '2026-05-06 09:12', 'via' => 'wa',      'cat' => 'food',      'acc' => 'gopay', 'label' => 'Kopi Tuku',                    'amount' => -28000,    'note' => '"kopi tuku 28rb"',           'merchant' => 'Kopi Tuku Cipete'],
            ['when' => '2026-05-06 08:30', 'via' => 'wa',      'cat' => 'transport', 'acc' => 'gopay', 'label' => 'Gojek ke meeting',            'amount' => -42000,    'note' => '"gojek 42rb ke kuningan"',   'merchant' => 'Gojek'],
            ['when' => '2026-05-05 18:40', 'via' => 'wa',      'cat' => 'food',      'acc' => 'cash',  'label' => 'Warteg dekat kos',            'amount' => -22000,    'note' => '"makan siang 22rb"',         'merchant' => 'Warteg Bahari'],
            ['when' => '2026-05-05 14:00', 'via' => 'manual',  'cat' => 'work',      'acc' => 'bca',   'label' => 'Invoice #2026-014 — PT Sinar', 'amount' => 12500000,  'note' => 'Project Q2 retainer',        'merchant' => 'PT Sinar Kreasi'],
            ['when' => '2026-05-05 11:15', 'via' => 'wa',      'cat' => 'shop',      'acc' => 'jago',  'label' => 'Tokopedia — keyboard',         'amount' => -1250000,  'note' => '/expense keyboard 1.25jt',   'merchant' => 'Tokopedia'],
            ['when' => '2026-05-04 20:05', 'via' => 'receipt', 'cat' => 'food',      'acc' => 'jago',  'label' => 'Indomaret',                    'amount' => -87500,    'note' => 'Foto struk',                 'merchant' => 'Indomaret Kemang'],
            ['when' => '2026-05-04 13:22', 'via' => 'wa',      'cat' => 'food',      'acc' => 'gopay', 'label' => 'GoFood — Padang',              'amount' => -52000,    'note' => '"makan siang padang 52rb"',  'merchant' => 'RM Sederhana'],
            ['when' => '2026-05-03 19:10', 'via' => 'wa',      'cat' => 'fun',       'acc' => 'jago',  'label' => 'CGV — Tiket bioskop',          'amount' => -75000,    'note' => '"nonton 75rb"',              'merchant' => 'CGV Plaza Indo'],
            ['when' => '2026-05-03 08:00', 'via' => 'wa',      'cat' => 'transport', 'acc' => 'gopay', 'label' => 'Grab',                         'amount' => -38000,    'note' => '"grab 38rb"',                'merchant' => 'Grab'],
            ['when' => '2026-05-02 15:30', 'via' => 'manual',  'cat' => 'bill',      'acc' => 'bca',   'label' => 'PLN listrik',                  'amount' => -425000,   'note' => 'Token PLN bulan Mei',        'merchant' => 'PLN'],
            ['when' => '2026-05-02 10:45', 'via' => 'wa',      'cat' => 'health',    'acc' => 'jago',  'label' => 'Apotek K24',                   'amount' => -68000,    'note' => '"obat batuk 68rb"',          'merchant' => 'K24'],
            ['when' => '2026-05-01 22:00', 'via' => 'wa',      'cat' => 'food',      'acc' => 'gopay', 'label' => 'GoFood — Bakmi',               'amount' => -45000,    'note' => '"bakmi 45rb"',               'merchant' => 'Bakmi GM'],
            ['when' => '2026-05-01 14:00', 'via' => 'manual',  'cat' => 'save',      'acc' => 'bca',   'label' => 'Transfer ke Tabungan Liburan', 'amount' => -2000000,  'note' => 'Auto-saving',                'merchant' => 'Transfer'],
            ['when' => '2026-04-30 11:00', 'via' => 'wa',      'cat' => 'work',      'acc' => 'bca',   'label' => 'Top-up klien — Logo project',  'amount' => 3500000,   'note' => '/income 3.5jt logo project', 'merchant' => 'CV Maju Jaya'],
            ['when' => '2026-04-30 08:30', 'via' => 'wa',      'cat' => 'food',      'acc' => 'gopay', 'label' => 'Sarapan nasi uduk',            'amount' => -18000,    'note' => '"nasi uduk 18rb"',           'merchant' => 'Nasi Uduk Babe'],
            ['when' => '2026-04-29 20:30', 'via' => 'receipt', 'cat' => 'shop',      'acc' => 'jago',  'label' => 'Superindo',                    'amount' => -312500,   'note' => 'Foto struk groceries',       'merchant' => 'Superindo'],
            ['when' => '2026-04-29 12:00', 'via' => 'wa',      'cat' => 'food',      'acc' => 'gopay', 'label' => 'GoFood — Sushi',               'amount' => -118000,   'note' => '"sushi 118rb"',              'merchant' => 'Sushi Tei'],
            ['when' => '2026-04-28 16:00', 'via' => 'manual',  'cat' => 'bill',      'acc' => 'bca',   'label' => 'Internet IndiHome',            'amount' => -380000,   'note' => 'Bulanan',                    'merchant' => 'Telkom'],
            ['when' => '2026-04-28 09:00', 'via' => 'wa',      'cat' => 'transport', 'acc' => 'gopay', 'label' => 'Bensin motor',                 'amount' => -25000,    'note' => '"bensin 25rb"',              'merchant' => 'Pertamina'],
            ['when' => '2026-04-27 19:20', 'via' => 'wa',      'cat' => 'fun',       'acc' => 'jago',  'label' => 'Spotify Premium',              'amount' => -54990,    'note' => '"spotify"',                  'merchant' => 'Spotify'],
        ];
        Transaction::where('user_id', $user->id)->delete();
        foreach ($txData as $t) {
            Transaction::create([
                'user_id' => $user->id,
                'household_id' => $householdId,
                'account_id' => $accountIds[$t['acc']],
                'category_id' => $categoryIds[$t['cat']],
                'label' => $t['label'],
                'amount' => $t['amount'],
                'via' => $t['via'],
                'note' => $t['note'],
                'merchant' => $t['merchant'],
                'occurred_at' => $t['when'],
            ]);
        }

        $budgetData = [
            ['cat' => 'food',      'limit' => 2500000],
            ['cat' => 'transport', 'limit' => 800000],
            ['cat' => 'shop',      'limit' => 1500000],
            ['cat' => 'fun',       'limit' => 500000],
            ['cat' => 'bill',      'limit' => 1200000],
            ['cat' => 'health',    'limit' => 400000],
        ];
        $period = '2026-05';
        foreach ($budgetData as $b) {
            Budget::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category_id' => $categoryIds[$b['cat']],
                    'period' => $period,
                ],
                ['monthly_limit' => $b['limit'], 'household_id' => $householdId],
            );
        }

        $goalData = [
            ['label' => 'Liburan ke Jepang',    'target' => 25000000, 'current' => 14200000, 'due_label' => 'Okt 2026', 'color' => '#ec4899'],
            ['label' => 'Dana darurat 6 bulan', 'target' => 36000000, 'current' => 22500000, 'due_label' => 'Des 2026', 'color' => '#1f8a5b'],
            ['label' => 'MacBook baru',         'target' => 28000000, 'current' => 8400000,  'due_label' => 'Mar 2027', 'color' => '#0e0e0c'],
        ];
        Goal::where('user_id', $user->id)->delete();
        foreach ($goalData as $g) {
            Goal::create(array_merge($g, ['user_id' => $user->id, 'household_id' => $householdId]));
        }

        $convData = [
            ['slug' => 'kasku',    'name' => 'Kasku Bot',         'last_message' => 'Tercatat ✅ Kopi Tuku Rp28.000', 'last_at_label' => '09:12', 'unread' => 0, 'online' => true,  'pinned' => true],
            ['slug' => 'reminder', 'name' => 'Reminder Tagihan',  'last_message' => 'IndiHome jatuh tempo besok',     'last_at_label' => '08:00', 'unread' => 1, 'online' => false, 'pinned' => false],
            ['slug' => 'weekly',   'name' => 'Weekly Report',     'last_message' => 'Laporan minggu lalu siap dilihat', 'last_at_label' => 'Sen', 'unread' => 0, 'online' => false, 'pinned' => false],
            ['slug' => 'budget',   'name' => 'Budget Alert',      'last_message' => 'Belanja 104% dari budget bulanan', 'last_at_label' => 'Min', 'unread' => 2, 'online' => false, 'pinned' => false],
        ];
        Conversation::where('user_id', $user->id)->delete();
        foreach ($convData as $c) {
            Conversation::create(array_merge($c, ['user_id' => $user->id]));
        }
    }
}
