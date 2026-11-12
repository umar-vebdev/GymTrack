<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Contracts;

use DateTimeImmutable;

/**
 * Публичное представление занятия в расписании для каталога залов (без внутренних ID и данных клиентов).
 */
final class PublicClassSession
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $title,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly int $capacity,
        public readonly int $availableSeats,
        public readonly ?string $trainerPublicId,
        public readonly ?string $trainerName,
    ) {}
}
