<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт модуля Companies: проверка принадлежности зала компании (для Trainers и других модулей).
 */
interface GymLookupInterface
{
    /**
     * Существует ли зал с указанным ID внутри указанной компании.
     */
    public function existsInCompany(int $gymId, int $companyId): bool;
}
