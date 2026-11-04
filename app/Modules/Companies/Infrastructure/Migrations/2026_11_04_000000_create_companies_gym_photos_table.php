<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies_gym_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('order');
            $table->timestamp('created_at');
            $table->index(['company_id', 'gym_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies_gym_photos');
    }
};
