<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\ListExpiringMemberships;
use App\Modules\Memberships\Domain\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListExpiringMembershipsController extends Controller
{
    public function __construct(
        private readonly ListExpiringMemberships $listExpiringMemberships,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(Request $request): JsonResponse
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

        $memberships = $this->listExpiringMemberships->execute($companyId);

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
            ], $memberships),
        ]);
    }
}
