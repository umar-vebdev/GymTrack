<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

/**
 * Полная замена имени и описания (`bio = null` очищает описание); `isActive = null` оставляет активность без изменений.
 */
final readonly class UpdateTrainerCommand
{
    public function __construct(
        public int $companyId,
        public int $trainerId,
        public string $name,
        public ?string $bio = null,
        public ?bool $isActive = null,
    ) {}
}
