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
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period', 16);
            $table->bigInteger('price_minor');
            $table->string('currency', 3)->default('TJS');
            $table->integer('max_gyms');
            $table->integer('max_staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
        });

        DB::statement('ALTER TABLE billing_plans ADD CONSTRAINT billing_plans_price_minor_check CHECK (price_minor >= 0)');
        DB::statement('ALTER TABLE billing_plans ADD CONSTRAINT billing_plans_max_gyms_check CHECK (max_gyms >= 1)');
        DB::statement('ALTER TABLE billing_plans ADD CONSTRAINT billing_plans_max_staff_check CHECK (max_staff >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};

