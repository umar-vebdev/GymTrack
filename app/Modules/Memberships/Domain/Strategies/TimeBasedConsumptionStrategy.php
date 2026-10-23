<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Strategies;

use App\Modules\Memberships\Domain\Membership;
use DateTimeImmutable;

/**
 * Стратегия списания для абонементов по сроку, безлимитов и разовых посещений:
 * не уменьшает счетчик посещений, абонемент действует до даты окончания (endsAt).
 */
final class TimeBasedConsumptionStrategy implements ConsumptionStrategy
{
    public function consume(Membership $membership, DateTimeImmutable $at): void
    {
        // Для безлимитных и временных абонементов списание занятий не производится.
        // Защита от повторных посещений за день и проверка срока действия
        // валидируются правилами визита и сроком годности абонемента.
    }
}
