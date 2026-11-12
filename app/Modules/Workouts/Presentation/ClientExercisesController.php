<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workouts\Application\CreateExercise;
use App\Modules\Workouts\Application\CreateExerciseCommand;
use App\Modules\Workouts\Application\ListExercises;
use App\Modules\Workouts\Application\UpdateExercise;
use App\Modules\Workouts\Application\UpdateExerciseCommand;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Presentation\Requests\ListExercisesRequest;
use App\Modules\Workouts\Presentation\Requests\StoreExerciseRequest;
use App\Modules\Workouts\Presentation\Requests\UpdateExerciseRequest;
use App\Modules\Workouts\Presentation\Resources\ExerciseResource;
use Illuminate\Http\JsonResponse;

final class ClientExercisesController extends Controller
{
    public function __construct(
        private readonly ListExercises $listExercises,
        private readonly CreateExercise $createExercise,
        private readonly UpdateExercise $updateExercise,
    ) {}

    public function index(ListExercisesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $muscleGroup = $request->validated('muscle_group');
        $q = $request->validated('q');

        $items = $this->listExercises->forClient(
            userId: $user->id,
            muscleGroup: is_string($muscleGroup) ? MuscleGroup::from($muscleGroup) : null,
            query: is_string($q) ? $q : null,
        );

        return response()->json([
            'data' => array_map(
                static fn (Exercise $exercise): array => ExerciseResource::toArray($exercise),
                $items,
            ),
        ]);
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, muscle_group: string, description?: string|null} $data */
        $data = $request->validated();

        $exercise = $this->createExercise->execute(new CreateExerciseCommand(
            userId: $user->id,
            name: $data['name'],
            muscleGroup: MuscleGroup::from($data['muscle_group']),
            description: $data['description'] ?? null,
        ));

        return response()->json(['data' => ExerciseResource::toArray($exercise)], 201);
    }

    public function update(UpdateExerciseRequest $request, int $exercise): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, muscle_group: string, description?: string|null, is_active?: bool|null} $data */
        $data = $request->validated();

        try {
            $updated = $this->updateExercise->execute(new UpdateExerciseCommand(
                exerciseId: $exercise,
                userId: $user->id,
                name: $data['name'],
                muscleGroup: MuscleGroup::from($data['muscle_group']),
                description: $data['description'] ?? null,
                isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                    ? (bool) $data['is_active']
                    : null,
            ));
        } catch (WorkoutResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => ExerciseResource::toArray($updated)]);
    }
}
