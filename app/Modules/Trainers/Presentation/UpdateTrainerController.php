<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Trainers\Application\UpdateTrainer;
use App\Modules\Trainers\Application\UpdateTrainerCommand;
use App\Modules\Trainers\Domain\Exceptions\TrainerNotFound;
use App\Modules\Trainers\Presentation\Requests\UpdateTrainerRequest;
use App\Modules\Trainers\Presentation\Resources\TrainerResource;
use Illuminate\Http\JsonResponse;

final class UpdateTrainerController extends Controller
{
    public function __construct(
        private readonly UpdateTrainer $updateTrainer,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(UpdateTrainerRequest $request, int $trainer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Редактировать профиль тренера может только владелец компании.',
                ],
            ], 403);
        }

        /** @var array{name: string, bio?: string|null, is_active?: bool|int|string|null} $data */
        $data = $request->validated();

        $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null
            ? (bool) $data['is_active']
            : null;

        try {
            $updated = $this->updateTrainer->execute(new UpdateTrainerCommand(
                companyId: $companyId,
                trainerId: $trainer,
                name: $data['name'],
                bio: $data['bio'] ?? null,
                isActive: $isActive,
            ));
        } catch (TrainerNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => TrainerResource::toArray($updated)]);
    }
}
