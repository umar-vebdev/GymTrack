<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Trainers\Domain\Exceptions\TrainerGymNotFound;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;

final class ListGymTrainers
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly TrainerRepositoryInterface $trainers,
    ) {}

    /**
     * @return list<Trainer>
     *
     * @throws TrainerGymNotFound зал не существует или принадлежит другой компании
     */
    public function execute(int $companyId, int $gymId): array
    {
        if (!$this->gyms->existsInCompany($gymId, $companyId)) {
            throw TrainerGymNotFound::withId($gymId);
        }

        return $this->trainers->listForGym($companyId, $gymId);
    }
}
