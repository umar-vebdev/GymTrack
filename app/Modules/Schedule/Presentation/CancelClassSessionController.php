<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\CancelClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Resources\ClassSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelClassSessionController extends Controller
{
    public function __construct(
        private readonly CancelClassSession $cancelClassSession,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(Request $request, int $session): JsonResponse
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

        try {
            $cancelled = $this->cancelClassSession->execute($companyId, $session);
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => ClassSessionResource::toArray($cancelled),
        ]);
    }
}
