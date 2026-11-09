<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;
use App\Modules\Trainers\Domain\Exceptions\TrainerNotFound;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;

final class UpdateTrainer
{
    public function __construct(private readonly TrainerRepositoryInterface $trainers) {}

    /**
     * @throws TrainerNotFound тренер не существует или принадлежит другой компании
     * @throws InvalidTrainerProfile
     */
    public function execute(UpdateTrainerCommand $command): Trainer
    {
        $trainer = $this->trainers->findById($command->companyId, $command->trainerId)
            ?? throw TrainerNotFound::withId($command->trainerId);

        $trainer->updateProfile($command->name, $command->bio);

        if ($command->isActive === true) {
            $trainer->activate();
        } elseif ($command->isActive === false) {
            $trainer->deactivate();
        }

        $this->trainers->save($trainer);

        return $trainer;
    }
}
