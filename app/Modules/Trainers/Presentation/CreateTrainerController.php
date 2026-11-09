<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Trainers\Application\CreateTrainer;
use App\Modules\Trainers\Application\CreateTrainerCommand;
use App\Modules\Trainers\Domain\Exceptions\TrainerGymNotFound;
use App\Modules\Trainers\Presentation\Requests\StoreTrainerRequest;
use App\Modules\Trainers\Presentation\Resources\TrainerResource;
use Illuminate\Http\JsonResponse;

final class CreateTrainerController extends Controller
{
    public function __construct(
        private readonly CreateTrainer $createTrainer,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(StoreTrainerRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Добавлять тренеров может только владелец компании.',
                ],
            ], 403);
        }

        /** @var array{gym_id: int|string, name: string, bio?: string|null} $data */
        $data = $request->validated();

        try {
            $trainer = $this->createTrainer->execute(new CreateTrainerCommand(
                companyId: $companyId,
                gymId: (int) $data['gym_id'],
                name: $data['name'],
                bio: $data['bio'] ?? null,
            ));
        } catch (TrainerGymNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => TrainerResource::toArray($trainer)], 201);
    }
}
