<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use DomainException;

/**
 * Доменный отказ в публикации или обновлении отзыва о зале (Бизнес-ТЗ 7).
 */
final class GymReviewDenied extends DomainException
{
    public const VISIT_REQUIRED = 'VISIT_REQUIRED_FOR_REVIEW';

    private function __construct(
        private readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function visitRequired(): self
    {
        return new self(
            self::VISIT_REQUIRED,
            'Отзыв можно оставить только после подтверждённого посещения этого зала.',
        );
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
