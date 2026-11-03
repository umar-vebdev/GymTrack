<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\UpdateGymProfile;
use App\Modules\Companies\Application\UpdateGymProfileCommand;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Presentation\Requests\UpdateGymProfileRequest;
use App\Modules\Companies\Presentation\Resources\GymProfileResource;
use Illuminate\Http\JsonResponse;

final class UpdateGymProfileController extends Controller
{
    public function __construct(
        private readonly UpdateGymProfile $updateGymProfile,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(UpdateGymProfileRequest $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Редактировать витрину может только владелец компании.',
                ],
            ], 403);
        }

        $data = $request->validated();

        try {
            $profile = $this->updateGymProfile->execute(new UpdateGymProfileCommand(
                companyId: $companyId,
                gymId: $gym,
                description: $data['description'] ?? null,
                address: $data['address'] ?? null,
                latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
                longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
                phone: $data['phone'] ?? null,
                workingHours: $data['working_hours'] ?? [],
                amenities: array_values($data['amenities'] ?? []),
            ));
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => GymProfileResource::toArray($profile)]);
    }
}
