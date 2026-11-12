<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Resources;

use App\Modules\Workouts\Domain\Exercise;

final class ExerciseResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id(),
            'public_id' => $exercise->publicId(),
            'is_platform' => $exercise->isPlatform(),
            'name' => $exercise->name(),
            'muscle_group' => $exercise->muscleGroup()->value,
            'description' => $exercise->description(),
            'is_active' => $exercise->isActive(),
        ];
    }
}

