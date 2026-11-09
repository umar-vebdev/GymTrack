<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\ListClassTemplates;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Requests\ListClassTemplatesRequest;
use App\Modules\Schedule\Presentation\Resources\ClassTemplateResource;
use Illuminate\Http\JsonResponse;

final class ListClassTemplatesController extends Controller
{
    public function __construct(
        private readonly ListClassTemplates $listClassTemplates,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(ListClassTemplatesRequest $request): JsonResponse
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

        $gymId = (int) $request->validated('gym_id');
        $onlyActive = $request->has('only_active') && $request->input('only_active') !== null
            ? $request->boolean('only_active')
            : null;

        try {
            $templates = $this->listClassTemplates->execute($companyId, $gymId, $onlyActive);
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (ClassTemplate $template): array => ClassTemplateResource::toArray($template),
                $templates,
            ),
        ]);
    }
}
