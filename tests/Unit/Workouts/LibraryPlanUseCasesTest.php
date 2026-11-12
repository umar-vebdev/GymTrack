<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Application\CreateLibraryPlan;
use App\Modules\Workouts\Application\CreateLibraryPlanCommand;
use App\Modules\Workouts\Application\GetLibraryPlan;
use App\Modules\Workouts\Application\ListLibraryPlans;
use App\Modules\Workouts\Application\PlanItemInput;
use App\Modules\Workouts\Application\UpdateLibraryPlan;
use App\Modules\Workouts\Application\UpdateLibraryPlanCommand;
use App\Modules\Workouts\Application\WorkoutPlanAssembler;
use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutPlan;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class LibraryPlanUseCasesTest extends TestCase
{
    public function test_it_creates_updates_lists_and_gets_library_plans(): void
    {
        $exerciseRepo = new InMemoryExerciseRepository([
            new Exercise(1, '01E00000000000000000000001', null, 'Squat', MuscleGroup::LEGS, null, true),
            new Exercise(2, '01E00000000000000000000002', null, 'Plank', MuscleGroup::CORE, null, true),
            new Exercise(3, '01E00000000000000000000003', null, 'Old Move', MuscleGroup::ARMS, null, false),
            new Exercise(4, '01E00000000000000000000004', 50, 'Client Move', MuscleGroup::CHEST, null, true),
        ]);
        $planRepo = new InMemoryWorkoutPlanRepository;
        $assembler = new WorkoutPlanAssembler($exerciseRepo);

        $create = new CreateLibraryPlan($planRepo, $assembler);
        $update = new UpdateLibraryPlan($planRepo, $assembler);
        $list = new ListLibraryPlans($planRepo, $assembler);
        $get = new GetLibraryPlan($planRepo, $assembler);

        $draft = $create->execute(new CreateLibraryPlanCommand(
            title: 'Base Plan',
            description: 'Initial',
            level: PlanLevel::Beginner,
            isPublished: false,
            items: [new PlanItemInput(exerciseId: 1, targetSets: 3, targetReps: 10, targetWeightGrams: 60000, restSeconds: 90)],
        ));

        $this->assertFalse($draft->plan->isPublished());
        $this->assertSame('Squat', $draft->exercisesById[1]->name());
        $this->assertSame([], $list->forClientLibrary());

        $published = $update->execute(new UpdateLibraryPlanCommand(
            planId: $draft->plan->id(),
            title: 'Base Plan v2',
            description: 'Published',
            level: PlanLevel::Intermediate,
            items: [
                new PlanItemInput(exerciseId: 2, targetSets: 3, targetReps: 30, restSeconds: 45),
                new PlanItemInput(exerciseId: 1, targetSets: 4, targetReps: 8, targetWeightGrams: 70000),
            ],
            isPublished: true,
        ));

        $this->assertTrue($published->plan->isVisibleInLibrary());
        $this->assertCount(1, $list->forClientLibrary());
        $this->assertCount(2, $published->exercisesById);
        $this->assertSame('Base Plan v2', $get->forClientLibrary($published->plan->id())->plan->title());

        // Неактивное или личное упражнение клиента не может попасть в план платформы
        try {
            $create->execute(new CreateLibraryPlanCommand(
                title: 'Bad Inactive',
                description: null,
                level: PlanLevel::Beginner,
                items: [new PlanItemInput(exerciseId: 3, targetSets: 3, targetReps: 10)],
            ));
            $this->fail('Expected InvalidWorkoutPlan for inactive exercise.');
        } catch (InvalidWorkoutPlan) {
            $this->addToAssertionCount(1);
        }

        try {
            $create->execute(new CreateLibraryPlanCommand(
                title: 'Bad Client Exercise',
                description: null,
                level: PlanLevel::Beginner,
                items: [new PlanItemInput(exerciseId: 4, targetSets: 3, targetReps: 10)],
            ));
            $this->fail('Expected WorkoutResourceNotFound for client exercise.');
        } catch (WorkoutResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(WorkoutResourceNotFound::class);
        $get->forPlatform(9999);
    }
}

final class InMemoryExerciseRepository implements ExerciseRepositoryInterface
{
    /** @var array<int, Exercise> */
    private array $byId = [];

    /** @param list<Exercise> $seed */
    public function __construct(array $seed = [])
    {
        foreach ($seed as $exercise) {
            $this->byId[$exercise->id()] = $exercise;
        }
    }

    public function create(?int $userId, string $name, MuscleGroup $muscleGroup, ?string $description = null): Exercise
    {
        $id = count($this->byId) + 1;
        $exercise = new Exercise($id, '01E'.str_pad((string) $id, 23, '0', STR_PAD_LEFT), $userId, $name, $muscleGroup, $description, true);
        $this->byId[$id] = $exercise;

        return $exercise;
    }

    public function save(Exercise $exercise): void
    {
        $this->byId[$exercise->id()] = $exercise;
    }

    public function findPlatformById(int $id): ?Exercise
    {
        $exercise = $this->byId[$id] ?? null;

        return $exercise !== null && $exercise->isPlatform() ? $exercise : null;
    }

    public function findOwnedById(int $userId, int $id): ?Exercise
    {
        $exercise = $this->byId[$id] ?? null;

        return $exercise !== null && $exercise->isOwnedBy($userId) ? $exercise : null;
    }

    public function findAvailableForClient(int $userId, int $id): ?Exercise
    {
        $exercise = $this->byId[$id] ?? null;
        if ($exercise === null || !$exercise->isActive()) {
            return null;
        }

        return ($exercise->isPlatform() || $exercise->isOwnedBy($userId)) ? $exercise : null;
    }

    public function findByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->byId[$id])) {
                $result[$id] = $this->byId[$id];
            }
        }

        return $result;
    }

    public function listForPlatform(?MuscleGroup $muscleGroup = null, ?string $query = null, ?bool $onlyActive = null): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Exercise $e): bool => $e->isPlatform(),
        ));
    }

    public function listForClient(int $userId, ?MuscleGroup $muscleGroup = null, ?string $query = null): array
    {
        return array_values($this->byId);
    }
}

final class InMemoryWorkoutPlanRepository implements WorkoutPlanRepositoryInterface
{
    /** @var array<int, WorkoutPlan> */
    private array $byId = [];

    private int $nextId = 1;

    private int $nextItemId = 1;

    public function create(
        ?int $userId,
        ?int $sourcePlanId,
        string $title,
        ?string $description,
        PlanLevel $level,
        bool $isPublished,
        array $items,
    ): WorkoutPlan {
        $id = $this->nextId++;
        $persistedItems = $this->persistItems($items);
        $plan = new WorkoutPlan($id, '01P'.str_pad((string) $id, 23, '0', STR_PAD_LEFT), $userId, $sourcePlanId, $title, $description, $level, $isPublished, true, $persistedItems);
        $this->byId[$id] = $plan;

        return $plan;
    }

    public function save(WorkoutPlan $plan): WorkoutPlan
    {
        $saved = new WorkoutPlan(
            $plan->id(),
            $plan->publicId(),
            $plan->userId(),
            $plan->sourcePlanId(),
            $plan->title(),
            $plan->description(),
            $plan->level(),
            $plan->isPublished(),
            $plan->isActive(),
            $this->persistItems($plan->items()),
        );
        $this->byId[$saved->id()] = $saved;

        return $saved;
    }

    public function findPlatformById(int $id): ?WorkoutPlan
    {
        $plan = $this->byId[$id] ?? null;

        return $plan !== null && $plan->isPlatform() ? $plan : null;
    }

    public function findPublishedLibraryById(int $id): ?WorkoutPlan
    {
        $plan = $this->byId[$id] ?? null;

        return $plan !== null && $plan->isVisibleInLibrary() ? $plan : null;
    }

    public function findOwnedById(int $userId, int $id): ?WorkoutPlan
    {
        $plan = $this->byId[$id] ?? null;

        return $plan !== null && $plan->isOwnedBy($userId) ? $plan : null;
    }

    public function listForPlatform(?PlanLevel $level = null, ?string $query = null, ?bool $onlyPublished = null, ?bool $onlyActive = null): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (WorkoutPlan $p): bool => $p->isPlatform(),
        ));
    }

    public function listLibraryForClient(?PlanLevel $level = null, ?string $query = null): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (WorkoutPlan $p): bool => $p->isVisibleInLibrary(),
        ));
    }

    public function listForClient(int $userId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (WorkoutPlan $p): bool => $p->isOwnedBy($userId) && $p->isActive(),
        ));
    }

    /**
     * @param  list<WorkoutPlanItem>  $items
     * @return list<WorkoutPlanItem>
     */
    private function persistItems(array $items): array
    {
        return array_map(
            fn (WorkoutPlanItem $item): WorkoutPlanItem => new WorkoutPlanItem(
                $this->nextItemId++,
                $item->exerciseId(),
                $item->sortOrder(),
                $item->targetSets(),
                $item->targetReps(),
                $item->targetWeightGrams(),
                $item->restSeconds(),
            ),
            $items,
        );
    }
}

