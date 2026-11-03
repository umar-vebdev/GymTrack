<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\SubmitGymProfileForModeration;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Presentation\Resources\GymProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubmitGymProfileController extends Controller
{
    public function __construct(
        private readonly SubmitGymProfileForModeration $submitProfile,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(Request $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Отправлять витрину на модерацию может только владелец компании.',
                ],
            ], 403);
        }

        try {
            $profile = $this->submitProfile->execute($companyId, $gym);
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => GymProfileResource::toArray($profile)]);
    }
}
