<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\ListCompanyMembershipRequests;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use App\Modules\Sales\Presentation\Requests\ListCompanyMembershipRequestsRequest;
use Illuminate\Http\JsonResponse;

final class ListCompanyMembershipRequestsController extends Controller
{
    public function __construct(
        private readonly ListCompanyMembershipRequests $listRequests,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(ListCompanyMembershipRequestsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        $statusInput = $request->validated('status');
        $status = is_string($statusInput) ? MembershipRequestStatus::tryFrom($statusInput) : null;

        $items = $this->listRequests->execute($companyId, $status);

        return response()->json([
            'data' => $items,
        ]);
    }
}
