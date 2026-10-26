<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\CancelMembership;
use App\Modules\Memberships\Domain\Exceptions\MembershipAlreadyCancelled;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotFound;
use App\Modules\Memberships\Presentation\Requests\CancelMembershipRequest;
use Illuminate\Http\JsonResponse;

final class CancelMembershipController extends Controller
{
    public function __construct(
        private readonly CancelMembership $cancelMembership,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, CancelMembershipRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Отменять абонемент может только владелец компании.',
            ]], 403);
        }

        try {
            $membership = $this->cancelMembership->execute(
                companyId: $companyId,
                membershipId: $id,
                reason: $request->string('reason')->toString(),
                cancelledByUserId: $user->id
            );
        } catch (MembershipNotFound $e) {
            return response()->json(['error' => [
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        } catch (MembershipAlreadyCancelled $e) {
            return response()->json(['error' => [
                'code' => 'MEMBERSHIP_ALREADY_CANCELLED',
                'message' => $e->getMessage(),
            ]], 409);
        }

        return response()->json([
            'data' => [
                'id' => $membership->id(),
                'status' => $membership->status()->value,
                'cancellation_reason' => $membership->cancellationReason(),
            ],
        ]);
    }
}
