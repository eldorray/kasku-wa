<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_invites', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->string('code', 16)->nullable()->change();
            $table->foreignId('invited_user_id')->nullable()->after('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->index(['invited_user_id', 'accepted_at', 'rejected_at'], 'inv_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::table('household_invites', function (Blueprint $table) {
            $table->dropIndex('inv_inbox_idx');
            $table->dropForeign(['invited_user_id']);
            $table->dropColumn(['invited_user_id', 'rejected_at']);
            $table->string('code', 16)->nullable(false)->change();
            $table->unique('code');
        });
    }
};
