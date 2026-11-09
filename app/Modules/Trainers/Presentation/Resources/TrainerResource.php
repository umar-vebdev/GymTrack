<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation\Resources;

use App\Modules\Trainers\Domain\Trainer;

final class TrainerResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Trainer $trainer): array
    {
        return [
            'id' => $trainer->id(),
            'public_id' => $trainer->publicId(),
            'gym_id' => $trainer->gymId(),
            'user_id' => $trainer->userId(),
            'name' => $trainer->name(),
            'bio' => $trainer->bio(),
            'is_active' => $trainer->isActive(),
        ];
    }
}
