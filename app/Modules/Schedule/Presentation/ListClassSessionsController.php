<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\ListClassSessions;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Requests\ListClassSessionsRequest;
use App\Modules\Schedule\Presentation\Resources\ClassSessionResource;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class ListClassSessionsController extends Controller
{
    public function __construct(
        private readonly ListClassSessions $listClassSessions,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(ListClassSessionsRequest $request): JsonResponse
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

        /** @var array{gym_id: int|string, from_date: string, to_date: string} $data */
        $data = $request->validated();
        $utc = new DateTimeZone('UTC');
        $from = new DateTimeImmutable($data['from_date'].' 00:00:00', $utc);
        $to = new DateTimeImmutable($data['to_date'].' 23:59:59', $utc);

        try {
            $sessions = $this->listClassSessions->execute(
                companyId: $companyId,
                gymId: (int) $data['gym_id'],
                from: $from,
                to: $to,
            );
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (ClassSession $session): array => ClassSessionResource::toArray($session),
                $sessions,
            ),
        ]);
    }
}
