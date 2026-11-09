<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Trainers\Application\ListGymTrainers;
use App\Modules\Trainers\Domain\Exceptions\TrainerGymNotFound;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Presentation\Requests\ListTrainersRequest;
use App\Modules\Trainers\Presentation\Resources\TrainerResource;
use Illuminate\Http\JsonResponse;

final class ListTrainersController extends Controller
{
    public function __construct(
        private readonly ListGymTrainers $listGymTrainers,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(ListTrainersRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        $gymId = (int) $request->validated('gym_id');

        try {
            $trainers = $this->listGymTrainers->execute($companyId, $gymId);
        } catch (TrainerGymNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (Trainer $trainer): array => TrainerResource::toArray($trainer),
                $trainers,
            ),
        ]);
    }
}
