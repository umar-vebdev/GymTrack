<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Schedule\Application\ListSessionBookings;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Presentation\Resources\ClassBookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Просмотр списка записавшихся на групповое занятие сотрудниками и тренерами зала.
 */
final class ListSessionBookingsController extends Controller
{
    public function __construct(
        private readonly ListSessionBookings $listSessionBookings,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(Request $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id)
            ?? $this->staffResolver->companyIdForTrainer($user->id);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников и тренеров зала.',
                ],
            ], 403);
        }

        try {
            $bookings = $this->listSessionBookings->execute($companyId, $session);
        } catch (ScheduleResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (ClassBooking $booking): array => ClassBookingResource::toArray($booking),
                $bookings,
            ),
        ]);
    }
}
