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
        Schema::create('schedule_bookings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('schedule_sessions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'client_id', 'idempotency_key'], 'schedule_bookings_company_client_idempotency_uniq');
            $table->index(['company_id', 'session_id', 'status'], 'schedule_bookings_company_session_status_idx');
            $table->index(['client_id', 'booked_at'], 'schedule_bookings_client_booked_at_idx');
        });

        DB::statement(
            "CREATE UNIQUE INDEX schedule_bookings_session_client_active_uniq
             ON schedule_bookings (session_id, client_id)
             WHERE status = 'booked'",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_bookings');
    }
};
