<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\ModerateGymProfile;
use App\Modules\Companies\Application\ModerateGymProfileCommand;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Presentation\Requests\ModerateGymProfileRequest;
use App\Modules\Companies\Presentation\Resources\GymProfileResource;
use Illuminate\Http\JsonResponse;

final class ModerateGymProfileController extends Controller
{
    public function __construct(
        private readonly ModerateGymProfile $moderateProfile,
    ) {}

    public function __invoke(ModerateGymProfileRequest $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->is_platform_admin) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Модерировать витрины залов может только администратор платформы.',
                ],
            ], 403);
        }

        try {
            $profile = $this->moderateProfile->execute(new ModerateGymProfileCommand(
                gymId: $gym,
                decision: (string) $request->validated('decision'),
                reason: $request->validated('reason') !== null ? (string) $request->validated('reason') : null,
                moderatorUserId: $user->id,
            ));
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }

        return response()->json(['data' => GymProfileResource::toArray($profile)]);
    }
}
