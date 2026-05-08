<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. transactions: explicit type + transfer pair + soft deletes + better indexes
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 16)->default('expense')->after('category_id');
            $table->unsignedBigInteger('transfer_pair_id')->nullable()->after('type');
            $table->softDeletes();
            $table->index(['user_id', 'category_id', 'occurred_at'], 'tx_user_cat_at_idx');
            $table->index('transfer_pair_id', 'tx_pair_idx');
        });

        // Backfill type from amount sign for existing rows.
        DB::statement("UPDATE transactions SET type = CASE WHEN amount > 0 THEN 'income' ELSE 'expense' END");

        // 2. categories: type (income/expense/both) + soft deletes
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 16)->default('both')->after('label');
            $table->softDeletes();
        });

        // 3. accounts: soft deletes + index
        Schema::table('accounts', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('user_id', 'acc_user_idx');
        });

        // 4. budgets: index + soft deletes
        Schema::table('budgets', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['user_id', 'period'], 'bud_user_period_idx');
        });

        // 5. goals: link to account, completed_at, soft deletes
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('color');
            $table->softDeletes();
        });

        // 6. users: phone_normalized unique for WhatsApp lookup
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_normalized', 32)->nullable()->unique()->after('phone');
        });

        // Backfill phone_normalized from phone.
        $users = DB::table('users')->whereNotNull('phone')->get(['id', 'phone']);
        foreach ($users as $u) {
            $digits = preg_replace('/\D+/', '', (string) $u->phone);
            if (str_starts_with($digits, '62')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if ($digits !== '') {
                DB::table('users')->where('id', $u->id)->update(['phone_normalized' => $digits]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'completed_at']);
            $table->dropSoftDeletes();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('bud_user_period_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('acc_user_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropSoftDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('tx_user_cat_at_idx');
            $table->dropIndex('tx_pair_idx');
            $table->dropColumn(['type', 'transfer_pair_id']);
            $table->dropSoftDeletes();
        });
    }
};
