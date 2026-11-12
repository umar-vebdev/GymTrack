<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutPlan;

/**
 * Тренировочный план в дневнике тренировок (Бизнес-ТЗ 9; D-016).
 * `userId === null` означает готовый план библиотеки платформы; `userId > 0` — личный план клиента.
 */
final class WorkoutPlan
{
    public const TITLE_MAX = 100;

    public const DESCRIPTION_MAX = 1000;

    private string $title;

    private ?string $description;

    /** @var list<WorkoutPlanItem> */
    private array $items;

    /**
     * @param  list<WorkoutPlanItem>  $items
     */
    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly ?int $userId,
        private readonly ?int $sourcePlanId,
        string $title,
        ?string $description,
        private PlanLevel $level,
        private bool $isPublished,
        private bool $isActive,
        array $items = [],
    ) {
        if ($userId !== null && $userId <= 0) {
            throw InvalidWorkoutPlan::invalidOwner();
        }
        if ($sourcePlanId !== null && ($sourcePlanId <= 0 || $userId === null)) {
            throw InvalidWorkoutPlan::invalidSourcePlan();
        }
        if ($userId !== null && $isPublished) {
            throw InvalidWorkoutPlan::onlyPlatformPlansCanBePublished();
        }

        $this->title = self::cleanTitle($title);
        $this->description = self::cleanDescription($description);
        $this->items = self::sortItems($items);
    }

    public static function cleanTitle(string $title): string
    {
        $trimmed = trim($title);

        if ($trimmed === '') {
            throw InvalidWorkoutPlan::titleRequired();
        }
        if (mb_strlen($trimmed) > self::TITLE_MAX) {
            throw InvalidWorkoutPlan::titleTooLong(self::TITLE_MAX);
        }

        return $trimmed;
    }

    public static function cleanDescription(?string $description): ?string
    {
        $trimmed = $description !== null ? trim($description) : null;

        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > self::DESCRIPTION_MAX) {
            throw InvalidWorkoutPlan::descriptionTooLong(self::DESCRIPTION_MAX);
        }

        return $trimmed;
    }

    /**
     * @param  list<WorkoutPlanItem>  $items
     */
    public function update(string $title, ?string $description, PlanLevel $level, array $items): void
    {
        $this->title = self::cleanTitle($title);
        $this->description = self::cleanDescription($description);
        $this->level = $level;
        $this->items = self::sortItems($items);
    }

    public function publish(): void
    {
        if (!$this->isPlatform()) {
            throw InvalidWorkoutPlan::onlyPlatformPlansCanBePublished();
        }

        $this->isPublished = true;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function isPlatform(): bool
    {
        return $this->userId === null;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }

    public function isVisibleInLibrary(): bool
    {
        return $this->isPlatform() && $this->isPublished && $this->isActive;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): string
    {
        return $this->publicId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function sourcePlanId(): ?int
    {
        return $this->sourcePlanId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function level(): PlanLevel
    {
        return $this->level;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return list<WorkoutPlanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @param  list<WorkoutPlanItem>  $items
     * @return list<WorkoutPlanItem>
     */
    private static function sortItems(array $items): array
    {
        usort(
            $items,
            static fn (WorkoutPlanItem $a, WorkoutPlanItem $b): int => $a->sortOrder() <=> $b->sortOrder(),
        );

        return $items;
    }
}
