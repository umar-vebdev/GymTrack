<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\ListGymProfilesForModeration;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Presentation\Requests\ListGymProfilesRequest;
use App\Modules\Companies\Presentation\Resources\GymProfileResource;
use Illuminate\Http\JsonResponse;

final class ListGymProfilesController extends Controller
{
    public function __construct(
        private readonly ListGymProfilesForModeration $listProfiles,
    ) {}

    public function __invoke(ListGymProfilesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->is_platform_admin) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Просматривать очередь модерации может только администратор платформы.',
                ],
            ], 403);
        }

        $statusValue = $request->validated('status');
        $status = $statusValue !== null
            ? ModerationStatus::from((string) $statusValue)
            : ModerationStatus::PENDING;

        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);

        $result = $this->listProfiles->execute($status, $page, $perPage);

        return response()->json([
            'data' => array_map(
                static fn (GymProfile $profile): array => GymProfileResource::toArray($profile),
                $result['items']
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}
