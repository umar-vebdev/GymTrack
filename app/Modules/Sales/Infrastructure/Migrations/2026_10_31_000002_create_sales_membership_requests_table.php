<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('gym_id')->constrained('gyms');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->foreignId('membership_type_id')->constrained('membership_types');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales_sales');
            $table->foreignId('membership_id')->nullable()->constrained('memberships');
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_membership_requests');
    }
};
