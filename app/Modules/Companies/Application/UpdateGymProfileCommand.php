<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

final class UpdateGymProfileCommand
{
    /**
     * @param  array<string, mixed>  $workingHours
     * @param  list<string>  $amenities
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly ?string $description,
        public readonly ?string $address,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $phone,
        public readonly array $workingHours,
        public readonly array $amenities,
        public readonly ?string $district = null,
    ) {}
}
