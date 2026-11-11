<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Schedule\Application\ListClassSessions;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Requests\ListClientGymScheduleRequest;
use App\Modules\Schedule\Presentation\Resources\ClassSessionResource;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class ListClientGymScheduleController extends Controller
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly ListClassSessions $listClassSessions,
    ) {}

    public function __invoke(ListClientGymScheduleRequest $request, int $gym): JsonResponse
    {
        $companyId = $this->gyms->findCompanyId($gym);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => ScheduleResourceNotFound::gym($gym)->getMessage(),
                ],
            ], 404);
        }

        /** @var array{from_date: string, to_date: string} $data */
        $data = $request->validated();
        $utc = new DateTimeZone('UTC');
        $from = new DateTimeImmutable($data['from_date'].' 00:00:00', $utc);
        $to = new DateTimeImmutable($data['to_date'].' 23:59:59', $utc);

        $sessions = $this->listClassSessions->execute($companyId, $gym, $from, $to);

        return response()->json([
            'data' => array_map(
                static fn (ClassSession $session): array => ClassSessionResource::toArray($session),
                $sessions,
            ),
        ]);
    }
}
