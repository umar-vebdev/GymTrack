<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\PayMembershipRequest;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Presentation\Requests\PayMembershipRequestRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

final class PayMembershipRequestController extends Controller
{
    public function __construct(
        private readonly PayMembershipRequest $payRequest,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(int $id, PayMembershipRequestRequest $request): JsonResponse
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

        /** @var string|null $idempotencyKey */
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $result = $this->payRequest->execute(
                requestId: $id,
                companyId: $companyId,
                staffUserId: $user->id,
                paymentMethod: PaymentMethod::from((string) $request->validated('payment_method')),
                idempotencyKey: $idempotencyKey
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
