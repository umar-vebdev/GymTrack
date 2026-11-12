<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Schedule\Application\ListClientBookings;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingStatus;
use App\Modules\Schedule\Presentation\Requests\ListClientBookingsRequest;
use App\Modules\Schedule\Presentation\Resources\ClassBookingResource;
use Illuminate\Http\JsonResponse;

/**
 * Клиентский HTTP-эндпоинт просмотра своих записей на групповые занятия.
 */
final class ListClientBookingsController extends Controller
{
    public function __construct(
        private readonly ListClientBookings $listClientBookings,
    ) {}

    public function __invoke(ListClientBookingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /**
         * @var array{
         *     page?: int|string|null,
         *     per_page?: int|string|null,
         *     company_id?: int|string|null,
         *     status?: string|null,
         * } $data
         */
        $data = $request->validated();

        $page = isset($data['page']) ? (int) $data['page'] : 1;
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $status = isset($data['status']) ? ClassBookingStatus::from($data['status']) : null;

        $result = $this->listClientBookings->execute(
            userId: $user->id,
            companyId: $companyId,
            status: $status,
            page: $page,
            perPage: $perPage,
        );

        return response()->json([
            'data' => array_map(
                static fn (ClassBooking $booking): array => ClassBookingResource::toArray($booking),
                $result['items'],
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}
