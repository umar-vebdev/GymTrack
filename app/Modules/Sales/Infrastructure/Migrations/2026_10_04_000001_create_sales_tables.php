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
        Schema::create('sales_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->string('status')->default('completed');
            $table->string('payment_method');
            $table->bigInteger('total_minor');
            $table->string('currency', 3);
            $table->foreignId('accepted_by_user_id')->constrained('users');
            $table->timestamp('paid_at');
            $table->text('refund_reason')->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'client_id']);
        });

        DB::statement('ALTER TABLE sales_sales ADD CONSTRAINT sales_sales_total_minor_check CHECK (total_minor >= 0)');

        Schema::create('sales_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('sales_products');
            $table->foreignId('membership_type_id')->nullable()->constrained('membership_types');
            $table->foreignId('issued_membership_id')->nullable()->constrained('memberships');
            $table->string('name');
            $table->bigInteger('unit_price_minor');
            $table->string('currency', 3);
            $table->integer('quantity');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE sales_sale_items ADD CONSTRAINT sales_sale_items_unit_price_minor_check CHECK (unit_price_minor >= 0)');
        DB::statement('ALTER TABLE sales_sale_items ADD CONSTRAINT sales_sale_items_quantity_check CHECK (quantity >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_sale_items');
        Schema::dropIfExists('sales_sales');
    }
};

