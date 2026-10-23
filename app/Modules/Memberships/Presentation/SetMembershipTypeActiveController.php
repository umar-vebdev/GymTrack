<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\SetMembershipTypeActive;
use App\Modules\Memberships\Domain\MembershipTypeNotFound;
use App\Modules\Memberships\Presentation\Requests\SetMembershipTypeActiveRequest;
use Illuminate\Http\JsonResponse;

final class SetMembershipTypeActiveController extends Controller
{
    public function __construct(
        private readonly SetMembershipTypeActive $setActive,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, SetMembershipTypeActiveRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Изменять активность типа абонемента может только владелец.',
            ]], 403);
        }

        try {
            $this->setActive->execute($companyId, $id, $request->boolean('is_active'));
        } catch (MembershipTypeNotFound $e) {
            return response()->json(['error' => [
                'code' => 'MEMBERSHIP_TYPE_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json(['data' => ['updated' => true]]);
    }
}
