<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Memberships\Application\ListMyMemberships;
use App\Modules\Memberships\Contracts\ClientMembershipItemDto;
use App\Modules\Memberships\Presentation\Requests\ListMyMembershipsRequest;
use Illuminate\Http\JsonResponse;

final class ListMyMembershipsController extends Controller
{
    public function __construct(
        private readonly ListMyMemberships $listMyMemberships
    ) {}

    public function __invoke(ListMyMembershipsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var string|null $status */
        $status = $request->validated('status');
        /** @var int|null $companyId */
        $companyId = $request->validated('company_id') !== null ? (int) $request->validated('company_id') : null;

        $items = $this->listMyMemberships->execute(
            userId: $user->id,
            statusFilter: $status,
            companyId: $companyId
        );

        return response()->json([
            'data' => array_map(static fn (ClientMembershipItemDto $item): array => [
                'id' => $item->id,
                'company_id' => $item->companyId,
                'client_id' => $item->clientId,
                'type_id' => $item->typeId,
                'type_name' => $item->typeName,
                'kind' => $item->kind,
                'status' => $item->status,
                'remaining_visits' => $item->remainingVisits,
                'starts_at' => $item->startsAt?->format(DATE_ATOM),
                'ends_at' => $item->endsAt?->format(DATE_ATOM),
                'frozen_at' => $item->frozenAt?->format(DATE_ATOM),
                'freeze_days_used' => $item->freezeDaysUsed,
                'freeze_times_used' => $item->freezeTimesUsed,
                'is_usable' => $item->isUsable,
            ], $items),
        ]);
    }
}
