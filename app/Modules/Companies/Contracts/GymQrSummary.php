<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * DTO с данными зала, его секретным ключом и настройками геопроверки для генерации и проверки динамического QR-кода.
 */
final class GymQrSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $name,
        public readonly string $qrSecret,
        public readonly string $timezone = 'Asia/Dushanbe',
        public readonly bool $geoCheckEnabled = false,
        public readonly int $geoCheckRadiusMeters = 200,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {}
}
