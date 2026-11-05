<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies_gym_profiles', function (Blueprint $table): void {
            $table->string('district', 100)->nullable();
            $table->boolean('is_public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies_gym_profiles', function (Blueprint $table): void {
            $table->dropColumn(['district', 'is_public']);
        });
    }
};
