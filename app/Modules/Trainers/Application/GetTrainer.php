<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

use App\Modules\Trainers\Domain\Exceptions\TrainerNotFound;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;

final class GetTrainer
{
    public function __construct(private readonly TrainerRepositoryInterface $trainers) {}

    /**
     * @throws TrainerNotFound тренер не существует или принадлежит другой компании
     */
    public function execute(int $companyId, int $trainerId): Trainer
    {
        return $this->trainers->findById($companyId, $trainerId)
            ?? throw TrainerNotFound::withId($trainerId);
    }
}
