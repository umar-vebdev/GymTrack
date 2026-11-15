<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\CheckInByClientQr;
use App\Modules\Visits\Application\Commands\CheckInByClientQrCommand;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Presentation\Requests\CheckInByClientQrRequest;
use Illuminate\Http\JsonResponse;

final class CheckInByClientQrController extends Controller
{
    public function __construct(
        private readonly CheckInByClientQr $checkInByClientQr,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(int $id, CheckInByClientQrRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Пользователь не состоит в компании.',
                ],
            ], 403);
        }

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? mb_substr(trim($headerKey), 0, 128)
            : null;

        $rawDevice = $request->validated('device') ?? $request->userAgent();
        $device = is_string($rawDevice) && trim($rawDevice) !== ''
            ? mb_substr(trim($rawDevice), 0, 255)
            : null;

        try {
            $result = $this->checkInByClientQr->execute(new CheckInByClientQrCommand(
                companyId: $companyId,
                gymId: $id,
                staffUserId: $user->id,
                qrToken: (string) $request->validated('token'),
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
        } catch (InvalidQrToken) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_QR_TOKEN',
                    'message' => 'Код недействителен, попросите администратора.',
                ],
            ], 422);
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
