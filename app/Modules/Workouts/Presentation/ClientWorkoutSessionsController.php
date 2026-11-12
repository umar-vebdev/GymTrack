<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workouts\Application\CompleteWorkoutSession;
use App\Modules\Workouts\Application\CompleteWorkoutSessionCommand;
use App\Modules\Workouts\Application\GetWorkoutSession;
use App\Modules\Workouts\Application\ListWorkoutSessions;
use App\Modules\Workouts\Application\LogWorkoutSet;
use App\Modules\Workouts\Application\LogWorkoutSetCommand;
use App\Modules\Workouts\Application\StartWorkoutSession;
use App\Modules\Workouts\Application\StartWorkoutSessionCommand;
use App\Modules\Workouts\Application\WorkoutSessionView;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use App\Modules\Workouts\Presentation\Requests\CompleteWorkoutSessionRequest;
use App\Modules\Workouts\Presentation\Requests\ListWorkoutSessionsRequest;
use App\Modules\Workouts\Presentation\Requests\LogWorkoutSetRequest;
use App\Modules\Workouts\Presentation\Requests\StartWorkoutSessionRequest;
use App\Modules\Workouts\Presentation\Resources\WorkoutSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientWorkoutSessionsController extends Controller
{
    public function __construct(
        private readonly ListWorkoutSessions $listSessions,
        private readonly GetWorkoutSession $getSession,
        private readonly StartWorkoutSession $startSession,
        private readonly LogWorkoutSet $logSet,
        private readonly CompleteWorkoutSession $completeSession,
    ) {}

    public function index(ListWorkoutSessionsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->validated('status');

        $views = $this->listSessions->execute(
            userId: $user->id,
            status: is_string($status) ? WorkoutSessionStatus::from($status) : null,
        );

        return response()->json([
            'data' => array_map(
                static fn (WorkoutSessionView $view): array => WorkoutSessionResource::toArray($view),
                $views,
            ),
        ]);
    }

    public function store(StartWorkoutSessionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{plan_id?: int|null, title?: string|null} $data */
        $data = $request->validated();

        try {
            $view = $this->startSession->execute(new StartWorkoutSessionCommand(
                userId: $user->id,
                planId: isset($data['plan_id']) ? (int) $data['plan_id'] : null,
                title: $data['title'] ?? null,
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutSessionResource::toArray($view)], 201);
    }

    public function show(Request $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $view = $this->getSession->execute($user->id, $session);
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutSessionResource::toArray($view)]);
    }

    public function logSet(LogWorkoutSetRequest $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{exercise_id: int, weight_grams: int, reps: int} $data */
        $data = $request->validated();

        try {
            $view = $this->logSet->execute(new LogWorkoutSetCommand(
                userId: $user->id,
                sessionId: $session,
                exerciseId: (int) $data['exercise_id'],
                weightGrams: (int) $data['weight_grams'],
                reps: (int) $data['reps'],
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutSessionResource::toArray($view)], 201);
    }

    public function complete(CompleteWorkoutSessionRequest $request, int $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{cancel?: bool, notes?: string|null} $data */
        $data = $request->validated();

        try {
            $view = $this->completeSession->execute(new CompleteWorkoutSessionCommand(
                userId: $user->id,
                sessionId: $session,
                cancel: (bool) ($data['cancel'] ?? false),
                notes: $data['notes'] ?? null,
            ));
        } catch (WorkoutResourceNotFound $e) {
            return $this->notFoundResponse($e);
        }

        return response()->json(['data' => WorkoutSessionResource::toArray($view)]);
    }

    private function notFoundResponse(WorkoutResourceNotFound $e): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
        ], 404);
    }
}

