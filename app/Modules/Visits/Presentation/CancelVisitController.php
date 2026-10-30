<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\CancelVisit;
use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Presentation\Requests\CancelVisitRequest;
use Illuminate\Http\JsonResponse;

final class CancelVisitController extends Controller
{
    public function __construct(
        private readonly CancelVisit $cancelVisit,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(int $id, CancelVisitRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        $visit = $this->cancelVisit->execute(
            companyId: $companyId,
            visitId: $id,
            cancelledByUserId: $user->id,
            reason: (string) $request->validated('reason'),
        );

        return response()->json([
            'data' => self::serializeVisit($visit),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeVisit(Visit $visit): array
    {
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
            'registered_by_user_id' => $visit->registeredByUserId(),
            'cancel_reason' => $visit->cancelReason(),
            'cancelled_by_user_id' => $visit->cancelledByUserId(),
            'cancelled_at' => $visit->cancelledAt()?->format(DATE_ATOM),
        ];
    }
}

