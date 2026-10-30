<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\ListClientVisits;
use App\Modules\Visits\Domain\Visit;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListClientVisitsController extends Controller
{
    public function __construct(
        private readonly ListClientVisits $listClientVisits,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(int $id, Request $request): JsonResponse
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

        try {
            $visits = $this->listClientVisits->execute($companyId, $id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (Visit $visit): array => CancelVisitController::serializeVisit($visit),
                $visits,
            ),
        ]);
    }
}
