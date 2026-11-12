<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Application\ArchiveClientPlan;
use App\Modules\Workouts\Application\CopyLibraryPlanToClient;
use App\Modules\Workouts\Application\CreateClientPlan;
use App\Modules\Workouts\Application\CreateClientPlanCommand;
use App\Modules\Workouts\Application\GetClientPlan;
use App\Modules\Workouts\Application\ListClientPlans;
use App\Modules\Workouts\Application\PlanItemInput;
use App\Modules\Workouts\Application\UpdateClientPlan;
use App\Modules\Workouts\Application\UpdateClientPlanCommand;
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

final class ClientPlanUseCasesTest extends TestCase
{
    public function test_client_creates_updates_archives_and_copies_plans_with_isolation(): void
    {
        $exerciseRepo = $this->makeExerciseRepo([
            new Exercise(1, '01E00000000000000000000001', null, 'Squat', MuscleGroup::LEGS, null, true),
            new Exercise(2, '01E00000000000000000000002', 10, 'Client Dip', MuscleGroup::CHEST, null, true),
            new Exercise(3, '01E00000000000000000000003', 20, 'Stranger Exercise', MuscleGroup::BACK, null, true),
            new Exercise(4, '01E00000000000000000000004', 10, 'Inactive Personal', MuscleGroup::ARMS, null, false),
        ]);
        $planRepo = $this->makePlanRepo();
        $libraryPlan = $planRepo->create(
            userId: null,
            sourcePlanId: null,
            title: 'Library Full Body',
            description: 'From platform',
            level: PlanLevel::Beginner,
            isPublished: true,
            items: [new WorkoutPlanItem(0, 1, 0, 3, 10, 50000, 90)],
        );
        $draftLibraryPlan = $planRepo->create(null, null, 'Draft Library', null, PlanLevel::Advanced, false, []);

        $assembler = new WorkoutPlanAssembler($exerciseRepo);
        $create = new CreateClientPlan($planRepo, $assembler);
        $update = new UpdateClientPlan($planRepo, $assembler);
        $archive = new ArchiveClientPlan($planRepo);
        $list = new ListClientPlans($planRepo, $assembler);
        $get = new GetClientPlan($planRepo, $assembler);
        $copy = new CopyLibraryPlanToClient($planRepo, $assembler);

        // Создание своего плана из платформенного и личного упражнения
        $created = $create->execute(new CreateClientPlanCommand(
            userId: 10,
            title: 'My Upper Legs',
            description: 'Custom split',
            level: PlanLevel::Intermediate,
            items: [
                new PlanItemInput(exerciseId: 1, targetSets: 4, targetReps: 8),
                new PlanItemInput(exerciseId: 2, targetSets: 3, targetReps: 12),
            ],
        ));

        $this->assertFalse($created->plan->isPlatform());
        $this->assertFalse($created->plan->isPublished());
        $this->assertTrue($created->plan->isOwnedBy(10));
        $this->assertCount(2, $created->plan->items());

        // Запрет чужого личного упражнения и неактивного упражнения
        try {
            $create->execute(new CreateClientPlanCommand(10, 'Bad Stranger', null, PlanLevel::Beginner, [
                new PlanItemInput(exerciseId: 3, targetSets: 3, targetReps: 10),
            ]));
            $this->fail('Expected WorkoutResourceNotFound for stranger exercise.');
        } catch (WorkoutResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $create->execute(new CreateClientPlanCommand(10, 'Bad Inactive', null, PlanLevel::Beginner, [
                new PlanItemInput(exerciseId: 4, targetSets: 3, targetReps: 10),
            ]));
            $this->fail('Expected InvalidWorkoutPlan for inactive exercise.');
        } catch (InvalidWorkoutPlan) {
            $this->addToAssertionCount(1);
        }

        // Обновление и изоляция от другого пользователя
        $updated = $update->execute(new UpdateClientPlanCommand(
            userId: 10,
            planId: $created->plan->id(),
            title: 'My Upper v2',
            description: null,
            level: PlanLevel::Advanced,
            items: [new PlanItemInput(exerciseId: 2, targetSets: 5, targetReps: 6, restSeconds: 120)],
        ));
        $this->assertSame('My Upper v2', $updated->plan->title());
        $this->assertCount(1, $updated->plan->items());

        // Копирование из библиотеки платформы
        $copied = $copy->execute(10, $libraryPlan->id(), 'My Copy From Library');
        $this->assertSame($libraryPlan->id(), $copied->plan->sourcePlanId());
        $this->assertSame('My Copy From Library', $copied->plan->title());
        $this->assertSame(50000, $copied->plan->items()[0]->targetWeightGrams());
        $this->assertCount(2, $list->execute(10));

        // Нельзя скопировать неопубликованный план библиотеки
        try {
            $copy->execute(10, $draftLibraryPlan->id());
            $this->fail('Expected WorkoutResourceNotFound for draft library plan.');
        } catch (WorkoutResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        // Архивирование убирает план из активного списка, но сохраняет для просмотра по ID
        $archive->execute(10, $created->plan->id());
        $this->assertCount(1, $list->execute(10));
        $this->assertFalse($get->execute(10, $created->plan->id())->plan->isActive());

        // Чужой пользователь не видит план
        $this->expectException(WorkoutResourceNotFound::class);
        $get->execute(20, $copied->plan->id());
    }

    /**
     * @param  list<Exercise>  $seed
     */
    private function makeExerciseRepo(array $seed): ExerciseRepositoryInterface
    {
        return new class($seed) implements ExerciseRepositoryInterface
        {
            /** @var array<int, Exercise> */
            private array $byId = [];

            /** @param list<Exercise> $seed */
            public function __construct(array $seed)
            {
                foreach ($seed as $exercise) {
                    $this->byId[$exercise->id()] = $exercise;
                }
            }

            public function create(?int $userId, string $name, MuscleGroup $muscleGroup, ?string $description = null): Exercise
            {
                $id = count($this->byId) + 1;
                $e = new Exercise($id, '01E'.str_pad((string) $id, 23, '0', STR_PAD_LEFT), $userId, $name, $muscleGroup, $description, true);
                $this->byId[$id] = $e;

                return $e;
            }

            public function save(Exercise $exercise): void
            {
                $this->byId[$exercise->id()] = $exercise;
            }

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

    private function makePlanRepo(): WorkoutPlanRepositoryInterface
    {
        return new class implements WorkoutPlanRepositoryInterface
        {
            /** @var array<int, WorkoutPlan> */
            private array $byId = [];

            private int $nextId = 1;

            private int $nextItemId = 1;

            public function create(?int $userId, ?int $sourcePlanId, string $title, ?string $description, PlanLevel $level, bool $isPublished, array $items): WorkoutPlan
            {
                $id = $this->nextId++;
                $plan = new WorkoutPlan($id, '01P'.str_pad((string) $id, 23, '0', STR_PAD_LEFT), $userId, $sourcePlanId, $title, $description, $level, $isPublished, true, $this->persistItems($items));
                $this->byId[$id] = $plan;

                return $plan;
            }

            public function save(WorkoutPlan $plan): WorkoutPlan
            {
                $saved = new WorkoutPlan($plan->id(), $plan->publicId(), $plan->userId(), $plan->sourcePlanId(), $plan->title(), $plan->description(), $plan->level(), $plan->isPublished(), $plan->isActive(), $this->persistItems($plan->items()));
                $this->byId[$saved->id()] = $saved;

                return $saved;
            }

            public function findPlatformById(int $id): ?WorkoutPlan
            {
                return null;
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
                return [];
            }

            public function listLibraryForClient(?PlanLevel $level = null, ?string $query = null): array
            {
                return [];
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
                    fn (WorkoutPlanItem $item): WorkoutPlanItem => new WorkoutPlanItem($this->nextItemId++, $item->exerciseId(), $item->sortOrder(), $item->targetSets(), $item->targetReps(), $item->targetWeightGrams(), $item->restSeconds()),
                    $items,
                );
            }
        };
    }
}
