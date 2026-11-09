<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\UpdateClassTemplate;
use App\Modules\Schedule\Application\UpdateClassTemplateCommand;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Requests\UpdateClassTemplateRequest;
use App\Modules\Schedule\Presentation\Resources\ClassTemplateResource;
use Illuminate\Http\JsonResponse;

final class UpdateClassTemplateController extends Controller
{
    public function __construct(
        private readonly UpdateClassTemplate $updateClassTemplate,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(UpdateClassTemplateRequest $request, int $template): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        /**
         * @var array{
         *     trainer_id: int|string,
         *     title: string,
         *     description?: string|null,
         *     weekday: int|string,
         *     start_time_minute: int|string,
         *     duration_minutes: int|string,
         *     capacity: int|string,
         *     is_active?: bool|int|string|null
         * } $data
         */
        $data = $request->validated();

        $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null
            ? (bool) $data['is_active']
            : null;

        try {
            $updated = $this->updateClassTemplate->execute(new UpdateClassTemplateCommand(
                companyId: $companyId,
                templateId: $template,
                trainerId: (int) $data['trainer_id'],
                title: $data['title'],
                description: $data['description'] ?? null,
                weekday: (int) $data['weekday'],
                startTimeMinute: (int) $data['start_time_minute'],
                durationMinutes: (int) $data['duration_minutes'],
                capacity: (int) $data['capacity'],
                isActive: $isActive,
            ));
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => ClassTemplateResource::toArray($updated)]);
    }
}
