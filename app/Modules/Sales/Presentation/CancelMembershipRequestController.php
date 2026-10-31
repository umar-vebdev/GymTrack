<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\CancelMembershipRequest;
use App\Modules\Sales\Presentation\Requests\CancelMembershipRequestRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

final class CancelMembershipRequestController extends Controller
{
    public function __construct(
        private readonly CancelMembershipRequest $cancelRequest,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(int $id, CancelMembershipRequestRequest $request): JsonResponse
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

        try {
            $result = $this->cancelRequest->execute(
                requestId: $id,
                companyId: $companyId,
                cancelledByUserId: $user->id,
                reason: $request->validated('reason')
            );
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'BAD_REQUEST',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}

