<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичные данные витрины зала для модуля Catalog (Бизнес-ТЗ 4.2, 7; Тех. ТЗ 3.5, 3.7; D-010).
 */
final class PublicGymProfile
{
    /**
     * @param  array<string, mixed>  $workingHours
     * @param  list<string>  $amenities
     * @param  list<string>  $photos
     */
    public function __construct(
        public readonly string $publicId,
        public readonly int $gymId,
        public readonly int $companyId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $address,
        public readonly ?string $district,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $phone,
        public readonly array $workingHours,
        public readonly array $amenities,
        public readonly array $photos,
    ) {}
}
