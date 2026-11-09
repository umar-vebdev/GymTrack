<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;
use App\Modules\Trainers\Domain\Exceptions\TrainerGymNotFound;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;

final class CreateTrainer
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly TrainerRepositoryInterface $trainers,
    ) {}

    /**
     * @throws TrainerGymNotFound зал не существует или принадлежит другой компании
     * @throws InvalidTrainerProfile
     */
    public function execute(CreateTrainerCommand $command): Trainer
    {
        if (!$this->gyms->existsInCompany($command->gymId, $command->companyId)) {
            throw TrainerGymNotFound::withId($command->gymId);
        }

        return $this->trainers->create(
            $command->companyId,
            $command->gymId,
            $command->name,
            $command->bio,
        );
    }
}
