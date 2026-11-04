<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\DeleteGymPhoto;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\Exceptions\GymPhotoNotFound;
use App\Modules\Companies\Presentation\Policies\GymPhotoPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteGymPhotoController extends Controller
{
    public function __construct(
        private readonly DeleteGymPhoto $delete,
        private readonly StaffCompanyResolverInterface $staff,
        private readonly GymPhotoPolicy $policy,
    ) {}

    public function __invoke(Request $request, int $gym, int $photo): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staff->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Нет доступа к компании.']], 403);
        }

        $permission = $this->policy->delete($user, $companyId, $gym);
        if ($permission->denied()) {
            $status = $permission->status() ?? 403;

            return response()->json(['error' => [
                'code' => $status === 404 ? 'NOT_FOUND' : 'FORBIDDEN',
                'message' => $permission->message(),
            ]], $status);
        }

        try {
            $this->delete->execute($companyId, $gym, $photo);
        } catch (GymNotFound|GymPhotoNotFound $exception) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => $exception->getMessage()]], 404);
        }

        return response()->json(['data' => null]);
    }
}
