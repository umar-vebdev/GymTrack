<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure;

use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Infrastructure\Persistence\ClassTemplateModel;

final class EloquentClassTemplateRepository implements ClassTemplateRepositoryInterface
{
    public function create(
        int $companyId,
        int $gymId,
        int $trainerId,
        string $title,
        ?string $description,
        int $weekday,
        int $startTimeMinute,
        int $durationMinutes,
        int $capacity,
    ): ClassTemplate {
        $model = ClassTemplateModel::query()->create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'trainer_id' => $trainerId,
            'title' => ClassTemplate::cleanTitle($title),
            'description' => ClassTemplate::cleanDescription($description),
            'weekday' => ClassTemplate::guardWeekday($weekday),
            'start_time_minute' => ClassTemplate::guardStartTimeMinute($startTimeMinute),
            'duration_minutes' => ClassTemplate::guardDurationMinutes($durationMinutes),
            'capacity' => ClassTemplate::guardCapacity($capacity),
            'is_active' => true,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function save(ClassTemplate $template): void
    {
        ClassTemplateModel::query()
            ->where('company_id', $template->companyId())
            ->whereKey($template->id())
            ->update([
                'trainer_id' => $template->trainerId(),
                'title' => $template->title(),
                'description' => $template->description(),
                'weekday' => $template->weekday(),
                'start_time_minute' => $template->startTimeMinute(),
                'duration_minutes' => $template->durationMinutes(),
                'capacity' => $template->capacity(),
                'is_active' => $template->isActive(),
            ]);
    }

    public function findById(int $companyId, int $id): ?ClassTemplate
    {
        $model = ClassTemplateModel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForGym(int $companyId, int $gymId, ?bool $onlyActive = null): array
    {
        $query = ClassTemplateModel::query()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId);

        if ($onlyActive !== null) {
            $query->where('is_active', $onlyActive);
        }

        return array_values($query
            ->orderBy('weekday')
            ->orderBy('start_time_minute')
            ->orderBy('id')
            ->get()
            ->map(fn (ClassTemplateModel $model): ClassTemplate => $this->toDomain($model))
            ->all());
    }

    private function toDomain(ClassTemplateModel $model): ClassTemplate
    {
        return new ClassTemplate(
            id: $model->id,
            publicId: $model->public_id,
            companyId: $model->company_id,
            gymId: $model->gym_id,
            trainerId: $model->trainer_id,
            title: $model->title,
            description: $model->description,
            weekday: $model->weekday,
            startTimeMinute: $model->start_time_minute,
            durationMinutes: $model->duration_minutes,
            capacity: $model->capacity,
            isActive: $model->is_active,
        );
    }
}
