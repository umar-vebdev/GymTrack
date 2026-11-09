<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Resources;

use App\Modules\Schedule\Domain\ClassTemplate;

final class ClassTemplateResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ClassTemplate $template): array
    {
        return [
            'id' => $template->id(),
            'public_id' => $template->publicId(),
            'gym_id' => $template->gymId(),
            'trainer_id' => $template->trainerId(),
            'title' => $template->title(),
            'description' => $template->description(),
            'weekday' => $template->weekday(),
            'start_time_minute' => $template->startTimeMinute(),
            'duration_minutes' => $template->durationMinutes(),
            'capacity' => $template->capacity(),
            'is_active' => $template->isActive(),
        ];
    }
}
