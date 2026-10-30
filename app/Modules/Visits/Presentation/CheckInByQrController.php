<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Visits\Application\CheckInByQr;
use App\Modules\Visits\Application\CheckInResult;
use App\Modules\Visits\Application\Commands\CheckInByQrCommand;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Presentation\Requests\CheckInByQrRequest;
use Illuminate\Http\JsonResponse;

final class CheckInByQrController extends Controller
{
    public function __construct(
        private readonly CheckInByQr $checkInByQr,
    ) {}

    public function __invoke(CheckInByQrRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? mb_substr(trim($headerKey), 0, 128)
            : null;

        $rawDevice = $request->validated('device') ?? $request->userAgent();
        $device = is_string($rawDevice) && trim($rawDevice) !== ''
            ? mb_substr(trim($rawDevice), 0, 255)
            : null;

        try {
            $result = $this->checkInByQr->execute(new CheckInByQrCommand(
                userId: $user->id,
                qrToken: (string) $request->validated('token'),
                idempotencyKey: $idempotencyKey,
                device: $device,
            ));
        } catch (InvalidQrToken) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_QR_TOKEN',
                    'message' => 'Код недействителен, попросите администратора.',
                ],
            ], 422);
        } catch (CheckInDenied $e) {
            return response()->json([
                'error' => self::mapDenialError($e->reason),
            ], 422);
        }

        return response()->json([
            'data' => self::serializeResult($result),
        ], 201);
    }

    /**
     * @return array{code: string, message: string}
     */
    public static function mapDenialError(string $reason): array
    {
        return match ($reason) {
            CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED => [
                'code' => 'DAILY_LIMIT_EXCEEDED',
                'message' => 'Вы уже отмечены сегодня.',
            ],
            CheckInDenied::REASON_FROZEN => [
                'code' => 'MEMBERSHIP_FROZEN',
                'message' => 'Абонемент заморожен.',
            ],
            CheckInDenied::REASON_OUT_OF_TIME_WINDOW => [
                'code' => 'OUTSIDE_ALLOWED_TIME',
                'message' => 'Абонемент не подходит по времени суток.',
            ],
            default => [
                'code' => 'NO_ACTIVE_MEMBERSHIP',
                'message' => 'Нет активного абонемента для отметки.',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeResult(CheckInResult $result): array
    {
        $visit = $result->visit;

        return [
            'id' => $visit->id(),
            'company_id' => $visit->companyId(),
            'gym_id' => $visit->gymId(),
            'client_id' => $visit->clientId(),
            'membership_id' => $visit->membershipId(),
            'method' => $visit->method()->value,
            'status' => $visit->status()->value,
            'visited_at' => $visit->visitedAt()->format(DATE_ATOM),
            'visit_date' => $visit->visitDate(),
            'remaining_visits' => $result->remainingVisits,
        ];
    }
}
