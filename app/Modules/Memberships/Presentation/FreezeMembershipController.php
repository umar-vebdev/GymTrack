<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Memberships\Application\FreezeMembership;
use App\Modules\Memberships\Domain\Exceptions\MembershipFreezeNotAllowed;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotFound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FreezeMembershipController extends Controller
{
    public function __construct(
        private readonly FreezeMembership $freezeMembership,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, Request $request): JsonResponse
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

        try {
            $membership = $this->freezeMembership->execute($companyId, $id);
        } catch (MembershipNotFound $e) {
            return response()->json(['error' => [
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        } catch (MembershipFreezeNotAllowed $e) {
            return response()->json(['error' => [
                'code' => 'MEMBERSHIP_FREEZE_NOT_ALLOWED',
                'message' => $e->getMessage(),
            ]], 409);
        }

        return response()->json([
            'data' => [
                'id' => $membership->id(),
                'status' => $membership->status()->value,
                'ends_at' => $membership->endsAt()?->format(DATE_ATOM),
                'freeze_days_used' => $membership->freezeDaysUsed(),
                'freeze_times_used' => $membership->freezeTimesUsed(),
                'frozen_at' => $membership->frozenAt()?->format(DATE_ATOM),
            ],
        ]);
    }
}

