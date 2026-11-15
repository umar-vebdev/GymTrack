<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class GymReview
{
    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    public const MAX_COMMENT_LENGTH = 1000;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $userId,
        private readonly int $rating,
        private readonly ?string $comment,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
        self::validateRatingAndComment($rating, $comment);
    }

    public static function validateRatingAndComment(int $rating, ?string $comment): ?string
    {
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            throw new InvalidArgumentException(
                sprintf('Оценка зала должна быть от %d до %d.', self::MIN_RATING, self::MAX_RATING)
            );
        }

        if ($comment === null) {
            return null;
        }

        $trimmed = trim($comment);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed, 'UTF-8') > self::MAX_COMMENT_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Комментарий к отзыву не должен превышать %d символов.', self::MAX_COMMENT_LENGTH)
            );
        }

        return $trimmed;
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

    public function userId(): int
    {
        return $this->userId;
    }

    public function rating(): int
    {
        return $this->rating;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
