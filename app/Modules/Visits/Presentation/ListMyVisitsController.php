<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Visits\Application\ListMyVisits;
use App\Modules\Visits\Contracts\ClientVisitItemDto;
use App\Modules\Visits\Presentation\Requests\ListMyVisitsRequest;
use Illuminate\Http\JsonResponse;

final class ListMyVisitsController extends Controller
{
    public function __construct(
        private readonly ListMyVisits $listMyVisits
    ) {}

    public function __invoke(ListMyVisitsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);
        $companyId = $request->validated('company_id') !== null ? (int) $request->validated('company_id') : null;
        /** @var string|null $status */
        $status = $request->validated('status');

        $result = $this->listMyVisits->execute(
            userId: $user->id,
            companyId: $companyId,
            status: $status,
            page: $page,
            perPage: $perPage
        );

        return response()->json([
            'data' => array_map(static fn (ClientVisitItemDto $item): array => [
                'id' => $item->id,
                'company_id' => $item->companyId,
                'gym_id' => $item->gymId,
                'client_id' => $item->clientId,
                'membership_id' => $item->membershipId,
                'method' => $item->method,
                'status' => $item->status,
                'visited_at' => $item->visitedAt->format(DATE_ATOM),
                'cancel_reason' => $item->cancelReason,
                'cancelled_at' => $item->cancelledAt?->format(DATE_ATOM),
            ], $result['items']),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}

