<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Schedule\Application\BookClassSession;
use App\Modules\Schedule\Application\BookClassSessionCommand;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Resources\ClassBookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Клиентский HTTP-эндпоинт идемпотентной записи на групповое занятие.
 */
final class BookClassSessionController extends Controller
{
    public function __construct(
        private readonly BookClassSession $bookClassSession,
    ) {}

    public function __invoke(Request $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) ? $headerKey : null;

        try {
            $booking = $this->bookClassSession->execute(new BookClassSessionCommand(
                userId: $user->id,
                sessionId: $session,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        } catch (InvalidClassSession $e) {
            return response()->json([
                'error' => ['code' => 'CLASS_SESSION_UNAVAILABLE', 'message' => $e->getMessage()],
            ], 422);
        } catch (InvalidClassBooking $e) {
            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'data' => ClassBookingResource::toArray($booking),
        ], 201);
    }
}
