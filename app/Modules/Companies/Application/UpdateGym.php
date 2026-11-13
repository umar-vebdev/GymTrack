<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Обновление основных параметров зала компании (Бизнес-ТЗ 3, 4.1).
 */
final class UpdateGym
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
    ) {}

    public function execute(
        int $companyId,
        int $gymId,
        string $name,
        ?string $address,
        ?string $timezone = null,
    ): Gym {
        /** @var Gym */
        return DB::transaction(function () use ($companyId, $gymId, $name, $address, $timezone): Gym {
            $gym = $this->gyms->findByIdForUpdate($gymId);

            if ($gym === null || $gym->companyId() !== $companyId) {
                throw GymNotFound::withId($gymId);
            }

            $updated = $gym->withDetails(
                name: $name,
                address: $address,
                timezone: $timezone ?? $gym->timezone(),
            );

            $this->gyms->save($updated);

            return $updated;
        });
    }
}

