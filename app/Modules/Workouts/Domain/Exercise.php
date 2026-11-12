<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use App\Modules\Workouts\Domain\Exceptions\InvalidExercise;

/**
 * Упражнение в справочнике дневника тренировок (Бизнес-ТЗ 9; D-016).
 * `userId === null` означает системное упражнение от платформы; `userId > 0` — личное упражнение клиента.
 */
final class Exercise
{
    public const NAME_MAX = 100;

    public const DESCRIPTION_MAX = 1000;

    private string $name;

    private ?string $description;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly ?int $userId,
        string $name,
        private MuscleGroup $muscleGroup,
        ?string $description,
        private bool $isActive,
    ) {
        if ($userId !== null && $userId <= 0) {
            throw InvalidExercise::invalidOwner();
        }

        $this->name = self::cleanName($name);
        $this->description = self::cleanDescription($description);
    }

    public static function cleanName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidExercise::nameRequired();
        }
        if (mb_strlen($trimmed) > self::NAME_MAX) {
            throw InvalidExercise::nameTooLong(self::NAME_MAX);
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
            throw InvalidExercise::descriptionTooLong(self::DESCRIPTION_MAX);
        }

        return $trimmed;
    }

    public function update(string $name, MuscleGroup $muscleGroup, ?string $description): void
    {
        $this->name = self::cleanName($name);
        $this->muscleGroup = $muscleGroup;
        $this->description = self::cleanDescription($description);
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

    public function name(): string
    {
        return $this->name;
    }

    public function muscleGroup(): MuscleGroup
    {
        return $this->muscleGroup;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
