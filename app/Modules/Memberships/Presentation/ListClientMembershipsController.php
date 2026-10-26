<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\ListClientMemberships;
use App\Modules\Memberships\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Memberships\Domain\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListClientMembershipsController extends Controller
{
    public function __construct(
        private readonly ListClientMemberships $listClientMemberships,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Доступ только для сотрудников зала.',
            ]], 403);
        }

        try {
            $memberships = $this->listClientMemberships->execute($companyId, $id);
        } catch (ClientNotAvailable $e) {
            return response()->json(['error' => [
                'code' => 'CLIENT_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json([
            'data' => array_map(static fn (Membership $m): array => [
                'id' => $m->id(),
                'client_id' => $m->clientId(),
                'type_id' => $m->typeId(),
                'kind' => $m->rules()->kind->value,
                'status' => $m->status()->value,
                'remaining_visits' => $m->remainingVisits(),
                'starts_at' => $m->startsAt()?->format(DATE_ATOM),
                'ends_at' => $m->endsAt()?->format(DATE_ATOM),
                'freeze_days_used' => $m->freezeDaysUsed(),
                'freeze_times_used' => $m->freezeTimesUsed(),
                'frozen_at' => $m->frozenAt()?->format(DATE_ATOM),
                'cancellation_reason' => $m->cancellationReason(),
            ], $memberships),
        ]);
    }
}
