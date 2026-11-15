<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Application\CreateMembershipRequest;
use App\Modules\Sales\Presentation\Requests\CreateMembershipRequestRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

final class CreateMembershipRequestController extends Controller
{
    public function __construct(
        private readonly CreateMembershipRequest $createRequest
    ) {}

    public function __invoke(CreateMembershipRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var string|null $idempotencyKey */
        $idempotencyKey = $request->header('Idempotency-Key');

        /** @var int|string $gymId */
        $gymId = $request->validated('gym_id');
        /** @var int|string $membershipTypeId */
        $membershipTypeId = $request->validated('membership_type_id');

        try {
            $result = $this->createRequest->execute(
                userId: $user->id,
                gymId: $gymId,
                membershipTypeId: $membershipTypeId,
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
        ], 201);
    }
}
