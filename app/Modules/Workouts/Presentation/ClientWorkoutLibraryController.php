<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Workouts\Application\GetLibraryPlan;
use App\Modules\Workouts\Application\ListLibraryPlans;
use App\Modules\Workouts\Application\WorkoutPlanView;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Presentation\Requests\ListLibraryPlansRequest;
use App\Modules\Workouts\Presentation\Resources\WorkoutPlanResource;
use Illuminate\Http\JsonResponse;

final class ClientWorkoutLibraryController extends Controller
{
    public function __construct(
        private readonly ListLibraryPlans $listPlans,
        private readonly GetLibraryPlan $getPlan,
    ) {}

    public function index(ListLibraryPlansRequest $request): JsonResponse
    {
        $level = $request->validated('level');
        $q = $request->validated('q');

        $views = $this->listPlans->forClientLibrary(
            level: is_string($level) ? PlanLevel::from($level) : null,
            query: is_string($q) ? $q : null,
        );

        return response()->json([
            'data' => array_map(
                static fn (WorkoutPlanView $view): array => WorkoutPlanResource::toArray($view),
                $views,
            ),
        ]);
    }

    public function show(int $plan): JsonResponse
    {
        try {
            $view = $this->getPlan->forClientLibrary($plan);
        } catch (WorkoutResourceNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)]);
    }
}
