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
        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price_minor');
            $table->string('currency', 3);
            $table->integer('stock_quantity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        DB::statement('ALTER TABLE sales_products ADD CONSTRAINT sales_products_price_minor_check CHECK (price_minor >= 0)');
        DB::statement('ALTER TABLE sales_products ADD CONSTRAINT sales_products_stock_quantity_check CHECK (stock_quantity IS NULL OR stock_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_products');
    }
};
