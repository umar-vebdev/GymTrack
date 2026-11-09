<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_class_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('trainers_profiles')->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('start_time_minute');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'gym_id', 'weekday', 'start_time_minute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_class_templates');
    }
};
