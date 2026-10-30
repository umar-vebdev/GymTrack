<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('billing_plans');
            $table->string('status', 16);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
