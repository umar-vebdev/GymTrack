<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Trainers\Contracts\TrainerLookupInterface;

final class UpdateClassTemplate
{
    public function __construct(
        private readonly TrainerLookupInterface $trainers,
        private readonly ClassTemplateRepositoryInterface $templates,
    ) {}

    /**
     * @throws ScheduleResourceNotFound шаблон не найден/чужой или тренер не найден/неактивен в зале шаблона
     * @throws InvalidClassTemplate
     */
    public function execute(UpdateClassTemplateCommand $command): ClassTemplate
    {
        $template = $this->templates->findById($command->companyId, $command->templateId)
            ?? throw ScheduleResourceNotFound::template($command->templateId);

        if (!$this->trainers->existsActiveInGym($command->trainerId, $command->companyId, $template->gymId())) {
            throw ScheduleResourceNotFound::activeTrainerInGym($command->trainerId, $template->gymId());
        }

        $template->update(
            trainerId: $command->trainerId,
            title: $command->title,
            description: $command->description,
            weekday: $command->weekday,
            startTimeMinute: $command->startTimeMinute,
            durationMinutes: $command->durationMinutes,
            capacity: $command->capacity,
        );

        if ($command->isActive === true) {
            $template->activate();
        } elseif ($command->isActive === false) {
            $template->deactivate();
        }

        $this->templates->save($template);

        return $template;
    }
}
