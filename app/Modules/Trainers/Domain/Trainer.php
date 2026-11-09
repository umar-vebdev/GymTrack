<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Domain;

use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;

/**
 * Профиль тренера зала (Бизнес-ТЗ 2, 8). Не связан с аккаунтом входа: `userId` необязателен.
 * Инварианты имени и описания защищены самой сущностью (инкапсуляция, fail fast).
 */
final class Trainer
{
    public const NAME_MAX = 100;

    public const BIO_MAX = 1000;

    private string $name;

    private ?string $bio;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly ?int $userId,
        string $name,
        ?string $bio,
        private bool $isActive,
    ) {
        $this->name = self::cleanName($name);
        $this->bio = self::cleanBio($bio);
    }

    /**
     * Проверяет и нормализует имя: пробелы по краям удаляются, пустое имя недопустимо.
     */
    public static function cleanName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidTrainerProfile::nameRequired();
        }
        if (mb_strlen($trimmed) > self::NAME_MAX) {
            throw InvalidTrainerProfile::nameTooLong(self::NAME_MAX);
        }

        return $trimmed;
    }

    /**
     * Проверяет и нормализует описание: пустая строка превращается в null.
     */
    public static function cleanBio(?string $bio): ?string
    {
        $trimmed = $bio !== null ? trim($bio) : null;

        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > self::BIO_MAX) {
            throw InvalidTrainerProfile::bioTooLong(self::BIO_MAX);
        }

        return $trimmed;
    }

    public function updateProfile(string $name, ?string $bio): void
    {
        $this->name = self::cleanName($name);
        $this->bio = self::cleanBio($bio);
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): string
    {
        return $this->publicId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function gymId(): int
    {
        return $this->gymId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function bio(): ?string
    {
        return $this->bio;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
