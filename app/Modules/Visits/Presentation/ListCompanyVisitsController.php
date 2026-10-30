<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\ListCompanyVisits;
use App\Modules\Visits\Domain\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCompanyVisitsController extends Controller
{
    public function __construct(
        private readonly ListCompanyVisits $listCompanyVisits,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(Request $request): JsonResponse
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

        $visits = $this->listCompanyVisits->execute($companyId);

        return response()->json([
            'data' => array_map(
                static fn (Visit $visit): array => CancelVisitController::serializeVisit($visit),
                $visits,
            ),
        ]);
    }
}

