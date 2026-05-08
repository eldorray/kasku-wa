<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('default_currency', 3)->default('IDR');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member'); // owner|member|viewer
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['household_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('household_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('invited_phone', 32)->nullable();
            $table->string('invited_email')->nullable();
            $table->string('role', 16)->default('member');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['household_id', 'accepted_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_household_id')->nullable()->after('phone_normalized')->constrained('households')->nullOnDelete();
        });

        // Add household_id to entity tables (nullable first for backfill, then NOT NULL).
        foreach (['accounts', 'transactions', 'budgets', 'goals'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->foreignId('household_id')->nullable()->after('user_id')->constrained('households')->cascadeOnDelete();
                $table->index(['household_id']);
            });
        }

        // Backfill: one Personal household per user, claim all that user's data.
        $users = DB::table('users')->get(['id', 'name']);
        foreach ($users as $u) {
            $name = trim((string) ($u->name ?? '')) ?: ('User #'.$u->id);
            $slugBase = Str::slug('personal-'.$name);
            $slug = $slugBase;
            $i = 2;
            while (DB::table('households')->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.$i++;
            }

            $hid = DB::table('households')->insertGetId([
                'name' => 'Personal — '.$name,
                'slug' => $slug,
                'default_currency' => 'IDR',
                'created_by' => $u->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('household_user')->insert([
                'household_id' => $hid,
                'user_id' => $u->id,
                'role' => 'owner',
                'joined_at' => now(),
                'invited_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $u->id)->update(['current_household_id' => $hid]);

            foreach (['accounts', 'transactions', 'budgets', 'goals'] as $tbl) {
                DB::table($tbl)->where('user_id', $u->id)->update(['household_id' => $hid]);
            }
        }

        // Now enforce NOT NULL on household_id.
        foreach (['accounts', 'transactions', 'budgets', 'goals'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->unsignedBigInteger('household_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['accounts', 'transactions', 'budgets', 'goals'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                $table->dropForeign([$tbl.'_household_id_foreign']);
                $table->dropIndex([$tbl.'_household_id_index']);
                $table->dropColumn('household_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_household_id']);
            $table->dropColumn('current_household_id');
        });

        Schema::dropIfExists('household_invites');
        Schema::dropIfExists('household_user');
        Schema::dropIfExists('households');
    }
};
