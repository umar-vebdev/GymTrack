<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('method', 32);
            $table->string('status', 32)->default('completed');
            $table->timestamp('visited_at');
            $table->date('visit_date');
            $table->integer('daily_entry_number')->default(1);
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_id']);
            $table->index(['gym_id', 'visited_at']);
            $table->index(['company_id', 'visited_at']);
            $table->unique(['company_id', 'idempotency_key']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX visits_visits_client_gym_day_active_uniq
             ON visits_visits (client_id, gym_id, visit_date, daily_entry_number)
             WHERE status = 'completed'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('visits_visits');
    }
};
