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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('type_id')->constrained('membership_types');
            $table->string('status');
            $table->unsignedInteger('remaining_visits')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('freeze_days_used')->default(0);
            $table->unsignedSmallInteger('freeze_times_used')->default(0);
            $table->timestamp('frozen_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'client_id']);
            $table->index(['client_id', 'status']);
        });

        DB::statement('ALTER TABLE memberships ADD CONSTRAINT memberships_remaining_visits_check CHECK (remaining_visits IS NULL OR remaining_visits >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
