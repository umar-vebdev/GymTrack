<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Заполняет справочники и библиотечные данные приложения.
     */
    public function run(): void
    {
        $this->call([
            PlatformPlanSeeder::class,
            WorkoutsSeeder::class,
        ]);
    }
}
