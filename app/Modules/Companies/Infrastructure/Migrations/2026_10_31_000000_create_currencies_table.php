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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            [
                'id' => 1,
                'code' => 'TJS',
                'name' => 'Таджикский сомони',
                'symbol' => 'смн',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'RUB',
                'name' => 'Российский рубль',
                'symbol' => '₽',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'USD',
                'name' => 'Доллар США',
                'symbol' => '$',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'EUR',
                'name' => 'Евро',
                'symbol' => '€',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'code' => 'UZS',
                'name' => 'Узбекский сум',
                'symbol' => 'so\'m',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'code' => 'KGS',
                'name' => 'Киргизский сом',
                'symbol' => 'сом',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'code' => 'KZT',
                'name' => 'Казахстанский тенге',
                'symbol' => '₸',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
