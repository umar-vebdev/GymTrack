<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\CreateGym;
use App\Modules\Companies\Application\ListCompanyGyms;
use App\Modules\Companies\Application\UpdateGym;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Presentation\Requests\StoreGymRequest;
use App\Modules\Companies\Presentation\Requests\UpdateGymRequest;
use App\Modules\Companies\Presentation\Resources\GymResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GymsController extends Controller
{
    public function __construct(
        private readonly ListCompanyGyms $listGyms,
        private readonly CreateGym $createGym,
        private readonly UpdateGym $updateGym,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null) {
            return $this->forbiddenResponse('Просматривать залы компании могут только владелец или администратор.');
        }

        $gyms = $this->listGyms->execute($companyId);

        return response()->json([
            'data' => array_map(static fn (Gym $gym): array => GymResource::toArray($gym), $gyms),
        ]);
    }

    public function store(StoreGymRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return $this->forbiddenResponse('Создавать филиалы может только владелец компании.');
        }

        /** @var array{name: string, address?: string|null, timezone?: string} $data */
        $data = $request->validated();

        $gym = $this->createGym->execute(
            companyId: $companyId,
            name: $data['name'],
            address: $data['address'] ?? null,
            timezone: $data['timezone'] ?? Gym::DEFAULT_TIMEZONE,
        );

        return response()->json([
            'data' => GymResource::toArray($gym),
        ], 201);
    }

    public function update(UpdateGymRequest $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return $this->forbiddenResponse('Редактировать филиалы может только владелец компании.');
        }

        /** @var array{name: string, address?: string|null, timezone?: string|null} $data */
        $data = $request->validated();

        try {
            $updated = $this->updateGym->execute(
                companyId: $companyId,
                gymId: $gym,
                name: $data['name'],
                address: $data['address'] ?? null,
                timezone: $data['timezone'] ?? null,
            );
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => GymResource::toArray($updated),
        ]);
    }

    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'FORBIDDEN', 'message' => $message],
        ], 403);
    }
}

