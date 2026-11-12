<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Contracts;

/**
 * Публичное представление тренера для расписания и каталога (без внутренних ID и userId).
 */
final class PublicTrainerSummary
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly ?string $bio,
    ) {}
}

