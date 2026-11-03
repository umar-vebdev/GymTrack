<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface GymProfileRepositoryInterface
{
    public function findByGymId(int $gymId): ?GymProfile;

    /** Создаёт или обновляет витрину зала (company_id берётся из самого зала). */
    public function save(GymProfile $profile): void;
}
