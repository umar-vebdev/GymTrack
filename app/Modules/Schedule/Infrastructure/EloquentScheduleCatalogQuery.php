<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure;

use App\Modules\Schedule\Contracts\PublicClassSession;
use App\Modules\Schedule\Contracts\ScheduleCatalogQueryInterface;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Infrastructure\Persistence\ClassSessionModel;
use App\Modules\Trainers\Contracts\PublicTrainerQueryInterface;
use DateTimeImmutable;
use DateTimeZone;

final class EloquentScheduleCatalogQuery implements ScheduleCatalogQueryInterface
{
    public function __construct(
        private readonly PublicTrainerQueryInterface $trainers,
    ) {}

    public function listUpcomingForGym(
        int $gymId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
    ): array {
        $utc = new DateTimeZone('UTC');
        $utcFrom = $from->setTimezone($utc);
        $utcTo = $to->setTimezone($utc);
        $utcNow = $now->setTimezone($utc);

        $effectiveFrom = $utcFrom > $utcNow ? $utcFrom : $utcNow;
        if ($effectiveFrom > $utcTo) {
            return [];
        }

        $models = ClassSessionModel::query()
            ->withoutGlobalScopes()
            ->where('gym_id', $gymId)
            ->where('status', ClassSessionStatus::SCHEDULED->value)
            ->whereBetween('starts_at', [
                $effectiveFrom->format('Y-m-d H:i:s'),
                $utcTo->format('Y-m-d H:i:s'),
            ])
            ->orderBy('starts_at')
            ->orderBy('public_id')
            ->get([
                'public_id',
                'trainer_id',
                'title',
                'capacity',
                'booked_count',
                'starts_at',
                'ends_at',
            ]);

        if ($models->isEmpty()) {
            return [];
        }

        /** @var list<int> $trainerIds */
        $trainerIds = $models
            ->map(static fn (ClassSessionModel $model): int => (int) $model->trainer_id)
            ->unique()
            ->values()
            ->all();

        $trainersById = $this->trainers->findByIds($trainerIds);

        $result = [];
        foreach ($models as $model) {
            $trainer = $trainersById[(int) $model->trainer_id] ?? null;
            $capacity = (int) $model->capacity;
            $bookedCount = (int) $model->booked_count;

            $result[] = new PublicClassSession(
                publicId: $model->public_id,
                title: $model->title,
                startsAt: DateTimeImmutable::createFromInterface($model->starts_at)->setTimezone($utc),
                endsAt: DateTimeImmutable::createFromInterface($model->ends_at)->setTimezone($utc),
                capacity: $capacity,
                availableSeats: max(0, $capacity - $bookedCount),
                trainerPublicId: $trainer?->publicId,
                trainerName: $trainer?->name,
            );
        }

        return $result;
    }
}
