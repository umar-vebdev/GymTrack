<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Schedule\Application\CancelClassBooking;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Resources\ClassBookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Клиентский HTTP-эндпоинт идемпотентной отмены собственной записи на занятие.
 */
final class CancelClassBookingController extends Controller
{
    public function __construct(
        private readonly CancelClassBooking $cancelClassBooking,
    ) {}

    public function __invoke(Request $request, int $booking): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $cancelled = $this->cancelClassBooking->execute($user->id, $booking);
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        } catch (InvalidClassBooking $e) {
            return response()->json([
                'error' => ['code' => 'CLASS_BOOKING_CANCELLATION_NOT_ALLOWED', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'data' => ClassBookingResource::toArray($cancelled),
        ]);
    }
}
