<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('workouts_plans')->nullOnDelete();
            $table->string('title', 100);
            $table->string('status', 30);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at', 'id']);
            $table->index(['user_id', 'status', 'started_at']);
        });

        Schema::create('workouts_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('workouts_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('workouts_exercises')->restrictOnDelete();
            $table->unsignedSmallInteger('set_number');
            $table->unsignedInteger('weight_grams');
            $table->unsignedSmallInteger('reps');
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->unique(['session_id', 'exercise_id', 'set_number']);
            $table->index(['session_id', 'id']);
            $table->index(['exercise_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts_sets');
        Schema::dropIfExists('workouts_sessions');
    }
};

