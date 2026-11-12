<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workouts\Application\CreateLibraryPlan;
use App\Modules\Workouts\Application\CreateLibraryPlanCommand;
use App\Modules\Workouts\Application\GetLibraryPlan;
use App\Modules\Workouts\Application\ListLibraryPlans;
use App\Modules\Workouts\Application\UpdateLibraryPlan;
use App\Modules\Workouts\Application\UpdateLibraryPlanCommand;
use App\Modules\Workouts\Application\WorkoutPlanView;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Presentation\Requests\ListLibraryPlansRequest;
use App\Modules\Workouts\Presentation\Requests\StoreLibraryPlanRequest;
use App\Modules\Workouts\Presentation\Requests\UpdateLibraryPlanRequest;
use App\Modules\Workouts\Presentation\Requests\WorkoutPlanRequestData;
use App\Modules\Workouts\Presentation\Resources\WorkoutPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformWorkoutPlansController extends Controller
{
    public function __construct(
        private readonly ListLibraryPlans $listPlans,
        private readonly GetLibraryPlan $getPlan,
        private readonly CreateLibraryPlan $createPlan,
        private readonly UpdateLibraryPlan $updatePlan,
    ) {}

    public function index(ListLibraryPlansRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (($forbidden = $this->forbidNonPlatformAdmin($user)) !== null) {
            return $forbidden;
        }

        $level = $request->validated('level');
        $q = $request->validated('q');
        $onlyPublished = $request->has('only_published') ? $request->boolean('only_published') : null;
        $onlyActive = $request->has('only_active') ? $request->boolean('only_active') : null;

        $views = $this->listPlans->forPlatform(
            level: is_string($level) ? PlanLevel::from($level) : null,
            query: is_string($q) ? $q : null,
            onlyPublished: $onlyPublished,
            onlyActive: $onlyActive,
        );

        return response()->json([
            'data' => array_map(
                static fn (WorkoutPlanView $view): array => WorkoutPlanResource::toArray($view),
                $views,
            ),
        ]);
    }

    public function show(Request $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (($forbidden = $this->forbidNonPlatformAdmin($user)) !== null) {
            return $forbidden;
        }

        try {
            $view = $this->getPlan->forPlatform($plan);
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)]);
    }

    public function store(StoreLibraryPlanRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (($forbidden = $this->forbidNonPlatformAdmin($user)) !== null) {
            return $forbidden;
        }

        /** @var array{title: string, description?: string|null, level: string, is_published?: bool, items?: list<array<string, mixed>>} $data */
        $data = $request->validated();

        try {
            $view = $this->createPlan->execute(new CreateLibraryPlanCommand(
                title: $data['title'],
                description: $data['description'] ?? null,
                level: PlanLevel::from($data['level']),
                isPublished: (bool) ($data['is_published'] ?? false),
                items: WorkoutPlanRequestData::toItemInputs($data['items'] ?? []),
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)], 201);
    }

    public function update(UpdateLibraryPlanRequest $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (($forbidden = $this->forbidNonPlatformAdmin($user)) !== null) {
            return $forbidden;
        }

        /** @var array{title: string, description?: string|null, level: string, is_published?: bool|null, is_active?: bool|null, items?: list<array<string, mixed>>} $data */
        $data = $request->validated();

        try {
            $view = $this->updatePlan->execute(new UpdateLibraryPlanCommand(
                planId: $plan,
                title: $data['title'],
                description: $data['description'] ?? null,
                level: PlanLevel::from($data['level']),
                items: WorkoutPlanRequestData::toItemInputs($data['items'] ?? []),
                isPublished: array_key_exists('is_published', $data) && $data['is_published'] !== null
                    ? (bool) $data['is_published']
                    : null,
                isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                    ? (bool) $data['is_active']
                    : null,
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)]);
    }

    private function forbidNonPlatformAdmin(User $user): ?JsonResponse
    {
        if (!$user->is_platform_admin) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Управлять библиотекой планов платформы может только администратор платформы.',
                ],
            ], 403);
        }

        return null;
    }

    private function notFoundResponse(WorkoutResourceNotFound $e): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
        ], 404);
    }
}
