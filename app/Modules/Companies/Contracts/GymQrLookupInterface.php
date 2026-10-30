<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт модуля Companies: чтение данных зала и секретного ключа QR для модуля Visits.
 */
interface GymQrLookupInterface
{
    public function findById(int $gymId): ?GymQrSummary;
}
