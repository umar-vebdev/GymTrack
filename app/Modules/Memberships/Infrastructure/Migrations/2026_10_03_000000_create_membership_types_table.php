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
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('start_mode');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3);
            $table->unsignedInteger('visits_count')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedSmallInteger('daily_limit')->default(1);
            $table->unsignedSmallInteger('time_from_minute')->nullable();
            $table->unsignedSmallInteger('time_to_minute')->nullable();
            $table->unsignedSmallInteger('freeze_max_days')->default(0);
            $table->unsignedSmallInteger('freeze_max_times')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        DB::statement('ALTER TABLE membership_types ADD CONSTRAINT membership_types_daily_limit_check CHECK (daily_limit >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_types');
    }
};
