<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Application\CompleteWorkoutSession;
use App\Modules\Workouts\Application\CompleteWorkoutSessionCommand;
use App\Modules\Workouts\Application\GetExerciseProgress;
use App\Modules\Workouts\Application\GetWorkoutSession;
use App\Modules\Workouts\Application\ListWorkoutSessions;
use App\Modules\Workouts\Application\LogWorkoutSet;
use App\Modules\Workouts\Application\LogWorkoutSetCommand;
use App\Modules\Workouts\Application\StartWorkoutSession;
use App\Modules\Workouts\Application\StartWorkoutSessionCommand;
use App\Modules\Workouts\Application\WorkoutSessionAssembler;
use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseProgressSession;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSession;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use App\Modules\Workouts\Domain\WorkoutSet;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkoutSessionUseCasesTest extends TestCase
{
    public function test_it_runs_full_session_lifecycle_and_computes_exercise_progress(): void
    {
        $clock = new class implements Clock
        {
            public DateTimeImmutable $current;

            public function __construct()
            {
                $this->current = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
            }

            public function now(): DateTimeImmutable
            {
                return $this->current;
            }
        };

        $exercises = $this->makeExerciseRepo([
            new Exercise(1, '01E00000000000000000000001', null, 'Squat', MuscleGroup::LEGS, null, true),
            new Exercise(2, '01E00000000000000000000002', 99, 'Stranger Move', MuscleGroup::BACK, null, true),
            new Exercise(3, '01E00000000000000000000003', 10, 'Inactive Personal', MuscleGroup::ARMS, null, false),
        ]);
        $plans = $this->makePlanRepo([
            new WorkoutPlan(5, '01P00000000000000000000005', 10, null, 'Leg Split', null, PlanLevel::Intermediate, false, true, []),
        ]);
        $sessions = $this->makeSessionRepo();
        $assembler = new WorkoutSessionAssembler($exercises);

        $start = new StartWorkoutSession($sessions, $plans, $assembler, $clock);
        $logSet = new LogWorkoutSet($sessions, $exercises, $assembler, $clock);
        $complete = new CompleteWorkoutSession($sessions, $assembler, $clock);
        $list = new ListWorkoutSessions($sessions, $assembler);
        $get = new GetWorkoutSession($sessions, $assembler);
        $progress = new GetExerciseProgress($sessions, $exercises);

        // Старт тренировки по плану без явного заголовка подставляет название плана
        $s1 = $start->execute(new StartWorkoutSessionCommand(userId: 10, planId: 5));
        $this->assertSame('Leg Split', $s1->session->title());
        $this->assertSame(WorkoutSessionStatus::InProgress, $s1->session->status());

        $clock->current = new DateTimeImmutable('2026-11-12T10:15:00+00:00');
        $logSet->execute(new LogWorkoutSetCommand(10, $s1->session->id(), exerciseId: 1, weightGrams: 80000, reps: 8));

        $clock->current = new DateTimeImmutable('2026-11-12T10:20:00+00:00');
        $afterSecond = $logSet->execute(new LogWorkoutSetCommand(10, $s1->session->id(), exerciseId: 1, weightGrams: 90000, reps: 5));
        $this->assertSame('Squat', $afterSecond->exercisesById[1]->name());
        $this->assertCount(2, $afterSecond->session->sets());

        // Запрет чужих или неактивных упражнений
        try {
            $logSet->execute(new LogWorkoutSetCommand(10, $s1->session->id(), exerciseId: 2, weightGrams: 50000, reps: 10));
            $this->fail('Expected WorkoutResourceNotFound for stranger exercise.');
        } catch (WorkoutResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $logSet->execute(new LogWorkoutSetCommand(10, $s1->session->id(), exerciseId: 3, weightGrams: 50000, reps: 10));
            $this->fail('Expected InvalidWorkoutSession for inactive exercise.');
        } catch (InvalidWorkoutSession) {
            $this->addToAssertionCount(1);
        }

        $clock->current = new DateTimeImmutable('2026-11-12T10:45:00+00:00');
        $completed = $complete->execute(new CompleteWorkoutSessionCommand(10, $s1->session->id(), notes: 'Solid workout'));
        $this->assertSame(WorkoutSessionStatus::Completed, $completed->session->status());
        $this->assertCount(1, $list->execute(10, WorkoutSessionStatus::Completed));
        $this->assertSame($s1->session->id(), $get->execute(10, $s1->session->id())->session->id());

        // Сводка прогресса по приседу: 80*8 + 90*5 = 640000 + 450000 = 1090000
        $report = $progress->execute(10, 1);
        $this->assertSame(1, $report->sessionsCount);
        $this->assertSame(90000, $report->bestWeightGrams);
        $this->assertSame(2, $report->totalSets);
        $this->assertSame(1090000, $report->totalVolumeGrams);
        $this->assertSame(13, $report->entries[0]->totalReps);
    }

    /** @param list<Exercise> $seed */
    private function makeExerciseRepo(array $seed): ExerciseRepositoryInterface
    {
        return new class($seed) implements ExerciseRepositoryInterface
        {
            /** @var array<int, Exercise> */
            private array $byId = [];

            /** @param list<Exercise> $seed */
            public function __construct(array $seed)
            {
                foreach ($seed as $e) {
                    $this->byId[$e->id()] = $e;
                }
            }

            public function create(?int $userId, string $name, MuscleGroup $muscleGroup, ?string $description = null): Exercise
            {
                throw new \LogicException('Not used');
            }

            public function save(Exercise $exercise): void {}

            public function findPlatformById(int $id): ?Exercise
            {
                return null;
            }

            public function findOwnedById(int $userId, int $id): ?Exercise
            {
                return null;
            }

            public function findAvailableForClient(int $userId, int $id): ?Exercise
            {
                return null;
            }

            public function findByIds(array $ids): array
            {
                $out = [];
                foreach ($ids as $id) {
                    if (isset($this->byId[$id])) {
                        $out[$id] = $this->byId[$id];
                    }
                }

                return $out;
            }

            public function listForPlatform(?MuscleGroup $muscleGroup = null, ?string $query = null, ?bool $onlyActive = null): array
            {
                return [];
            }

            public function listForClient(int $userId, ?MuscleGroup $muscleGroup = null, ?string $query = null): array
            {
                return [];
            }
        };
    }

    /** @param list<WorkoutPlan> $seed */
    private function makePlanRepo(array $seed): WorkoutPlanRepositoryInterface
    {
        return new class($seed) implements WorkoutPlanRepositoryInterface
        {
            /** @var array<int, WorkoutPlan> */
            private array $byId = [];

            /** @param list<WorkoutPlan> $seed */
            public function __construct(array $seed)
            {
                foreach ($seed as $p) {
                    $this->byId[$p->id()] = $p;
                }
            }

            public function create(?int $userId, ?int $sourcePlanId, string $title, ?string $description, PlanLevel $level, bool $isPublished, array $items): WorkoutPlan
            {
                throw new \LogicException('Not used');
            }

            public function save(WorkoutPlan $plan): WorkoutPlan
            {
                return $plan;
            }

            public function findPlatformById(int $id): ?WorkoutPlan
            {
                return null;
            }

            public function findPublishedLibraryById(int $id): ?WorkoutPlan
            {
                $p = $this->byId[$id] ?? null;

                return $p !== null && $p->isVisibleInLibrary() ? $p : null;
            }

            public function findOwnedById(int $userId, int $id): ?WorkoutPlan
            {
                $p = $this->byId[$id] ?? null;

                return $p !== null && $p->isOwnedBy($userId) ? $p : null;
            }

            public function listForPlatform(?PlanLevel $level = null, ?string $query = null, ?bool $onlyPublished = null, ?bool $onlyActive = null): array
            {
                return [];
            }

            public function listLibraryForClient(?PlanLevel $level = null, ?string $query = null): array
            {
                return [];
            }

            public function listForClient(int $userId): array
            {
                return [];
            }
        };
    }

    private function makeSessionRepo(): WorkoutSessionRepositoryInterface
    {
        return new class implements WorkoutSessionRepositoryInterface
        {
            /** @var array<int, WorkoutSession> */
            private array $byId = [];

            private int $nextId = 1;

            public function start(int $userId, ?int $planId, string $title, DateTimeImmutable $startedAt): WorkoutSession
            {
                $id = $this->nextId++;
                $s = new WorkoutSession($id, '01S'.str_pad((string) $id, 23, '0', STR_PAD_LEFT), $userId, $planId, $title, WorkoutSessionStatus::InProgress, $startedAt);
                $this->byId[$id] = $s;

                return $s;
            }

            public function addSet(int $userId, int $sessionId, int $exerciseId, int $weightGrams, int $reps, DateTimeImmutable $performedAt): ?WorkoutSession
            {
                $s = $this->findOwnedById($userId, $sessionId);
                if ($s === null) {
                    return null;
                }
                $s->logSet($exerciseId, $weightGrams, $reps, $performedAt);

                return $s;
            }

            public function save(WorkoutSession $session): WorkoutSession
            {
                $this->byId[$session->id()] = $session;

                return $session;
            }

            public function findOwnedById(int $userId, int $id): ?WorkoutSession
            {
                $s = $this->byId[$id] ?? null;

                return $s !== null && $s->isOwnedBy($userId) ? $s : null;
            }

            public function listForClient(int $userId, ?WorkoutSessionStatus $status = null): array
            {
                return array_values(array_filter(
                    $this->byId,
                    static fn (WorkoutSession $s): bool => $s->isOwnedBy($userId) && ($status === null || $s->status() === $status),
                ));
            }

            public function listExerciseProgress(int $userId, int $exerciseId): array
            {
                $out = [];
                foreach ($this->byId as $s) {
                    if (!$s->isOwnedBy($userId) || $s->status() === WorkoutSessionStatus::Cancelled) {
                        continue;
                    }
                    $sets = array_values(array_filter($s->sets(), static fn (WorkoutSet $set): bool => $set->exerciseId() === $exerciseId));
                    if ($sets !== []) {
                        $out[] = new ExerciseProgressSession($s->id(), $s->publicId(), $s->title(), $s->status(), $s->startedAt(), $sets);
                    }
                }

                return $out;
            }
        };
    }
}
