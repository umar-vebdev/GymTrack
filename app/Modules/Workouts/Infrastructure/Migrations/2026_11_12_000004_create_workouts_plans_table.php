<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts_plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_plan_id')->nullable()->constrained('workouts_plans')->nullOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('level', 30);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_published', 'is_active', 'level']);
        });

        Schema::create('workouts_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('workouts_plans')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('workouts_exercises')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->unsignedSmallInteger('target_sets');
            $table->unsignedSmallInteger('target_reps');
            $table->unsignedInteger('target_weight_grams')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts_plan_items');
        Schema::dropIfExists('workouts_plans');
    }
};

