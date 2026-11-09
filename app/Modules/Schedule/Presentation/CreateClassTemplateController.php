<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\CreateClassTemplate;
use App\Modules\Schedule\Application\CreateClassTemplateCommand;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Requests\StoreClassTemplateRequest;
use App\Modules\Schedule\Presentation\Resources\ClassTemplateResource;
use Illuminate\Http\JsonResponse;

final class CreateClassTemplateController extends Controller
{
    public function __construct(
        private readonly CreateClassTemplate $createClassTemplate,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(StoreClassTemplateRequest $request): JsonResponse
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
         *     gym_id: int|string,
         *     trainer_id: int|string,
         *     title: string,
         *     description?: string|null,
         *     weekday: int|string,
         *     start_time_minute: int|string,
         *     duration_minutes: int|string,
         *     capacity: int|string
         * } $data
         */
        $data = $request->validated();

        try {
            $template = $this->createClassTemplate->execute(new CreateClassTemplateCommand(
                companyId: $companyId,
                gymId: (int) $data['gym_id'],
                trainerId: (int) $data['trainer_id'],
                title: $data['title'],
                description: $data['description'] ?? null,
                weekday: (int) $data['weekday'],
                startTimeMinute: (int) $data['start_time_minute'],
                durationMinutes: (int) $data['duration_minutes'],
                capacity: (int) $data['capacity'],
            ));
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => ClassTemplateResource::toArray($template)], 201);
    }
}
