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

    /**
     * Возвращает ID компании-владельца зала или null, если зал не существует.
     */
    public function findCompanyId(int $gymId): ?int;

    /**
     * Возвращает IANA часовой пояс зала или null, если зал не существует.
     */
    public function findTimezone(int $gymId): ?string;

    /**
     * Резолвит внутренний числовой ID зала по числовому id или публичному ULID (public_id).
     */
    public function resolveId(int|string $idOrPublicId): ?int;
}
