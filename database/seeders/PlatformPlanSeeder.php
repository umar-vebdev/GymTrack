<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Infrastructure\Persistence\PlatformPlanModel;
use Illuminate\Database\Seeder;

/**
 * Сидер базовых тарифных планов платформы GymTrack для залов (Бизнес-ТЗ 1.1; Тех. ТЗ 12).
 */
final class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plans() as $plan) {
            PlatformPlanModel::query()->updateOrCreate(
                [
                    'name' => $plan['name'],
                    'period' => $plan['period'],
                ],
                [
                    'price_minor' => $plan['price_minor'],
                    'currency' => $plan['currency'],
                    'max_gyms' => $plan['max_gyms'],
                    'max_staff' => $plan['max_staff'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     period: string,
     *     price_minor: int,
     *     currency: string,
     *     max_gyms: int,
     *     max_staff: int,
     * }>
     */
    private function plans(): array
    {
        return [
            [
                'name' => 'Старт',
                'period' => BillingPeriod::Month->value,
                'price_minor' => 29000,
                'currency' => 'TJS',
                'max_gyms' => 1,
                'max_staff' => 5,
            ],
            [
                'name' => 'Бизнес',
                'period' => BillingPeriod::Month->value,
                'price_minor' => 59000,
                'currency' => 'TJS',
                'max_gyms' => 3,
                'max_staff' => 15,
            ],
            [
                'name' => 'Сеть',
                'period' => BillingPeriod::Month->value,
                'price_minor' => 119000,
                'currency' => 'TJS',
                'max_gyms' => 10,
                'max_staff' => 50,
            ],
            [
                'name' => 'Старт (годовой)',
                'period' => BillingPeriod::Year->value,
                'price_minor' => 290000,
                'currency' => 'TJS',
                'max_gyms' => 1,
                'max_staff' => 5,
            ],
            [
                'name' => 'Бизнес (годовой)',
                'period' => BillingPeriod::Year->value,
                'price_minor' => 590000,
                'currency' => 'TJS',
                'max_gyms' => 3,
                'max_staff' => 15,
            ],
        ];
    }
}
