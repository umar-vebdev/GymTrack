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
        Schema::create('schedule_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('schedule_class_templates')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('trainers_profiles')->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['template_id', 'starts_at']);
            $table->index(['company_id', 'gym_id', 'starts_at']);
        });

        DB::statement(
            'ALTER TABLE schedule_sessions ADD CONSTRAINT schedule_sessions_booked_count_check '
            .'CHECK (booked_count >= 0 AND booked_count <= capacity)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sessions');
    }
};
