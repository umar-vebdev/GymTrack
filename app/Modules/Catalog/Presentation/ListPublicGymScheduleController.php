<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\GetPublicGymSchedule;
use App\Modules\Catalog\Presentation\Requests\ListPublicGymScheduleRequest;
use App\Modules\Catalog\Presentation\Resources\PublicClassSessionResource;
use App\Modules\Schedule\Contracts\PublicClassSession;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class ListPublicGymScheduleController extends Controller
{
    public function __construct(
        private readonly GetPublicGymSchedule $getPublicGymSchedule,
    ) {}

    public function __invoke(ListPublicGymScheduleRequest $request, string $publicId): JsonResponse
    {
        $utc = new DateTimeZone('UTC');
        /** @var string|null $rawFrom */
        $rawFrom = $request->validated('from_date');
        /** @var string|null $rawTo */
        $rawTo = $request->validated('to_date');

        $fromDate = $rawFrom !== null ? new DateTimeImmutable($rawFrom.' 00:00:00', $utc) : null;
        $toDate = $rawTo !== null ? new DateTimeImmutable($rawTo.' 00:00:00', $utc) : null;

        $result = $this->getPublicGymSchedule->execute($publicId, $fromDate, $toDate);

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Зал не найден.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (PublicClassSession $session): array => PublicClassSessionResource::toArray($session),
                $result->sessions,
            ),
            'meta' => [
                'from_date' => $result->fromDate->format('Y-m-d'),
                'to_date' => $result->toDate->format('Y-m-d'),
            ],
        ]);
    }
}

