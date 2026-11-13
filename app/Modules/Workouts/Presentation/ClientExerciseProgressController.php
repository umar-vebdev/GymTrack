<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workouts\Application\GetExerciseProgress;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Presentation\Resources\ExerciseProgressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientExerciseProgressController extends Controller
{
    public function __construct(
        private readonly GetExerciseProgress $getProgress,
    ) {}

    public function show(Request $request, int $exercise): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $view = $this->getProgress->execute($user->id, $exercise);
        } catch (WorkoutResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => ExerciseProgressResource::toArray($view)]);
    }
}
