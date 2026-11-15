<?php

declare(strict_types=1);

namespace App\Modules\Visits\Contracts;

/**
 * Узкий контракт проверки наличия завершённого посещения клиента в указанном зале (Бизнес-ТЗ 7; Тех. ТЗ 3.5; D-019).
 */
interface ClientGymVisitCheckInterface
{
    public function hasCompletedVisitInGym(int $userId, int $companyId, int $gymId): bool;
}
