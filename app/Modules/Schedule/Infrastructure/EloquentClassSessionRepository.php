<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure;

use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Infrastructure\Persistence\ClassSessionModel;
use DateTimeImmutable;
use DateTimeZone;

final class EloquentClassSessionRepository implements ClassSessionRepositoryInterface
{
    public function createIfNotExistsFromTemplate(
        ClassTemplate $template,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): ?ClassSession {
        $utcStarts = $startsAt->setTimezone(new DateTimeZone('UTC'));
        $utcEnds = $endsAt->setTimezone(new DateTimeZone('UTC'));

        if ($utcStarts >= $utcEnds) {
            throw InvalidClassSession::invalidTimeRange();
        }

        $startsFormatted = $utcStarts->format('Y-m-d H:i:s');

        $exists = ClassSessionModel::query()
            ->withoutGlobalScopes()
            ->where('template_id', $template->id())
            ->where('starts_at', $startsFormatted)
            ->exists();

        if ($exists) {
            return null;
        }

        $model = ClassSessionModel::query()->create([
            'company_id' => $template->companyId(),
            'gym_id' => $template->gymId(),
            'template_id' => $template->id(),
            'trainer_id' => $template->trainerId(),
            'title' => $template->title(),
            'capacity' => $template->capacity(),
            'booked_count' => 0,
            'starts_at' => $startsFormatted,
            'ends_at' => $utcEnds->format('Y-m-d H:i:s'),
            'status' => ClassSessionStatus::SCHEDULED->value,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function save(ClassSession $session): void
    {
        ClassSessionModel::query()
            ->where('company_id', $session->companyId())
            ->whereKey($session->id())
            ->update([
                'booked_count' => $session->bookedCount(),
                'status' => $session->status()->value,
            ]);
    }

    public function findCompanyId(int $id): ?int
    {
        $companyId = ClassSessionModel::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->value('company_id');

        return is_numeric($companyId) ? (int) $companyId : null;
    }

    public function findById(int $companyId, int $id): ?ClassSession
    {
        $model = ClassSessionModel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $companyId, int $id): ?ClassSession
    {
        $model = ClassSessionModel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForGymInPeriod(
        int $companyId,
        int $gymId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $utcFrom = $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $utcTo = $to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return array_values(ClassSessionModel::query()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->whereBetween('starts_at', [$utcFrom, $utcTo])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ClassSessionModel $model): ClassSession => $this->toDomain($model))
            ->all());
    }

    private function toDomain(ClassSessionModel $model): ClassSession
    {
        return new ClassSession(
            id: $model->id,
            publicId: $model->public_id,
            companyId: $model->company_id,
            gymId: $model->gym_id,
            templateId: $model->template_id,
            trainerId: $model->trainer_id,
            title: $model->title,
            capacity: $model->capacity,
            bookedCount: $model->booked_count,
            startsAt: DateTimeImmutable::createFromInterface($model->starts_at)->setTimezone(new DateTimeZone('UTC')),
            endsAt: DateTimeImmutable::createFromInterface($model->ends_at)->setTimezone(new DateTimeZone('UTC')),
            status: ClassSessionStatus::from($model->status),
        );
    }
}
