<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Trainers\Contracts\TrainerLookupInterface;

final class CreateClassTemplate
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly TrainerLookupInterface $trainers,
        private readonly ClassTemplateRepositoryInterface $templates,
    ) {}

    /**
     * @throws ScheduleResourceNotFound зал не найден/чужой или тренер не найден/неактивен в этом зале
     * @throws InvalidClassTemplate
     */
    public function execute(CreateClassTemplateCommand $command): ClassTemplate
    {
        if (!$this->gyms->existsInCompany($command->gymId, $command->companyId)) {
            throw ScheduleResourceNotFound::gym($command->gymId);
        }

        if (!$this->trainers->existsActiveInGym($command->trainerId, $command->companyId, $command->gymId)) {
            throw ScheduleResourceNotFound::activeTrainerInGym($command->trainerId, $command->gymId);
        }

        return $this->templates->create(
            companyId: $command->companyId,
            gymId: $command->gymId,
            trainerId: $command->trainerId,
            title: $command->title,
            description: $command->description,
            weekday: $command->weekday,
            startTimeMinute: $command->startTimeMinute,
            durationMinutes: $command->durationMinutes,
            capacity: $command->capacity,
        );
    }
}
