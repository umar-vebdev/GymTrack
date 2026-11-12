<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workouts\Application\ArchiveClientPlan;
use App\Modules\Workouts\Application\CopyLibraryPlanToClient;
use App\Modules\Workouts\Application\CreateClientPlan;
use App\Modules\Workouts\Application\CreateClientPlanCommand;
use App\Modules\Workouts\Application\GetClientPlan;
use App\Modules\Workouts\Application\ListClientPlans;
use App\Modules\Workouts\Application\UpdateClientPlan;
use App\Modules\Workouts\Application\UpdateClientPlanCommand;
use App\Modules\Workouts\Application\WorkoutPlanView;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Presentation\Requests\CopyLibraryPlanRequest;
use App\Modules\Workouts\Presentation\Requests\StoreClientPlanRequest;
use App\Modules\Workouts\Presentation\Requests\UpdateClientPlanRequest;
use App\Modules\Workouts\Presentation\Requests\WorkoutPlanRequestData;
use App\Modules\Workouts\Presentation\Resources\WorkoutPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientWorkoutPlansController extends Controller
{
    public function __construct(
        private readonly ListClientPlans $listPlans,
        private readonly GetClientPlan $getPlan,
        private readonly CreateClientPlan $createPlan,
        private readonly UpdateClientPlan $updatePlan,
        private readonly ArchiveClientPlan $archivePlan,
        private readonly CopyLibraryPlanToClient $copyLibraryPlan,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => array_map(
                static fn (WorkoutPlanView $view): array => WorkoutPlanResource::toArray($view),
                $this->listPlans->execute($user->id),
            ),
        ]);
    }

    public function show(Request $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $view = $this->getPlan->execute($user->id, $plan);
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)]);
    }

    public function store(StoreClientPlanRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{title: string, description?: string|null, level: string, items?: list<array<string, mixed>>} $data */
        $data = $request->validated();

        try {
            $view = $this->createPlan->execute(new CreateClientPlanCommand(
                userId: $user->id,
                title: $data['title'],
                description: $data['description'] ?? null,
                level: PlanLevel::from($data['level']),
                items: WorkoutPlanRequestData::toItemInputs($data['items'] ?? []),
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)], 201);
    }

    public function update(UpdateClientPlanRequest $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{title: string, description?: string|null, level: string, items?: list<array<string, mixed>>} $data */
        $data = $request->validated();

        try {
            $view = $this->updatePlan->execute(new UpdateClientPlanCommand(
                userId: $user->id,
                planId: $plan,
                title: $data['title'],
                description: $data['description'] ?? null,
                level: PlanLevel::from($data['level']),
                items: WorkoutPlanRequestData::toItemInputs($data['items'] ?? []),
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)]);
    }

    public function destroy(Request $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->archivePlan->execute($user->id, $plan);
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => null]);
    }

    public function copyFromLibrary(CopyLibraryPlanRequest $request, int $plan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $title = $request->validated('title');

        try {
            $view = $this->copyLibraryPlan->execute(
                userId: $user->id,
                libraryPlanId: $plan,
                title: is_string($title) && trim($title) !== '' ? $title : null,
            );
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutPlanResource::toArray($view)], 201);
    }

    private function notFoundResponse(WorkoutResourceNotFound $e): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
        ], 404);
    }
}
