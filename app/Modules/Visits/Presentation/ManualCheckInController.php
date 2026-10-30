<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\CheckInManually;
use App\Modules\Visits\Application\Commands\CheckInManuallyCommand;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Presentation\Requests\ManualCheckInRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class ManualCheckInController extends Controller
{
    public function __construct(
        private readonly CheckInManually $checkInManually,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(int $id, ManualCheckInRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? mb_substr(trim($headerKey), 0, 128)
            : null;

        $rawVisitedAt = $request->validated('visited_at');
        $visitedAt = is_string($rawVisitedAt) && trim($rawVisitedAt) !== ''
            ? (new DateTimeImmutable($rawVisitedAt))->setTimezone(new DateTimeZone('UTC'))
            : null;

        $rawDevice = $request->validated('device') ?? $request->userAgent();
        $device = is_string($rawDevice) && trim($rawDevice) !== ''
            ? mb_substr(trim($rawDevice), 0, 255)
            : null;

        try {
            $result = $this->checkInManually->execute(new CheckInManuallyCommand(
                companyId: $companyId,
                gymId: (int) $request->validated('gym_id'),
                clientId: $id,
                staffUserId: $user->id,
                visitedAt: $visitedAt,
                idempotencyKey: $idempotencyKey,
                device: $device,
            ));
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => [
                    'code' => 'GYM_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        } catch (CheckInDenied $e) {
            return response()->json([
                'error' => CheckInByQrController::mapDenialError($e->reason),
            ], 422);
        }

        return response()->json([
            'data' => CheckInByQrController::serializeResult($result),
        ], 201);
    }
}

