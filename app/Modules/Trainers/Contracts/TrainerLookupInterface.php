<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Contracts;

/**
 * Публичный контракт модуля Trainers для модуля Schedule: проверка существования активного тренера в зале компании.
 */
interface TrainerLookupInterface
{
    public function existsActiveInGym(int $trainerId, int $companyId, int $gymId): bool;
}
