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
        Schema::create('companies_gym_reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'user_id']);
            $table->index(['gym_id', 'created_at']);
        });

        DB::statement(
            'ALTER TABLE companies_gym_reviews ADD CONSTRAINT companies_gym_reviews_rating_range CHECK (rating >= 1 AND rating <= 5)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('companies_gym_reviews');
    }
};
