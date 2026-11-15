<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts_sessions', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable()->after('notes');
            $table->unique(['user_id', 'idempotency_key'], 'workouts_sessions_user_idempotency_unique');
        });

        Schema::table('workouts_sets', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable()->after('performed_at');
            $table->unique(['session_id', 'idempotency_key'], 'workouts_sets_session_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workouts_sets', function (Blueprint $table): void {
            $table->dropUnique('workouts_sets_session_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('workouts_sessions', function (Blueprint $table): void {
            $table->dropUnique('workouts_sessions_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
