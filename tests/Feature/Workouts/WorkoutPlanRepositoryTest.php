<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkoutPlanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutPlanRepositoryInterface $plans;

    private ExerciseRepositoryInterface $exercises;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plans = $this->app->make(WorkoutPlanRepositoryInterface::class);
        $this->exercises = $this->app->make(ExerciseRepositoryInterface::class);
    }

    public function test_it_creates_saves_and_syncs_items_for_platform_and_client_plans(): void
    {
        $user = User::factory()->create();
        $squat = $this->exercises->create(null, 'Squat', MuscleGroup::LEGS);
        $bench = $this->exercises->create(null, 'Bench Press', MuscleGroup::CHEST);
        $curl = $this->exercises->create($user->id, 'My Curl', MuscleGroup::ARMS);

        $libraryPlan = $this->plans->create(
            userId: null,
            sourcePlanId: null,
            title: 'Starter Full Body',
            description: 'Three times a week',
            level: PlanLevel::Beginner,
            isPublished: true,
            items: [
                new WorkoutPlanItem(0, $bench->id(), 1, 3, 10, 50000, 90),
                new WorkoutPlanItem(0, $squat->id(), 0, 4, 8, 80000, 120),
            ],
        );

        $draftPlan = $this->plans->create(
            userId: null,
            sourcePlanId: null,
            title: 'Draft Advanced',
            description: null,
            level: PlanLevel::Advanced,
            isPublished: false,
            items: [],
        );

        $personalPlan = $this->plans->create(
            userId: $user->id,
            sourcePlanId: $libraryPlan->id(),
            title: 'My Copy',
            description: null,
            level: PlanLevel::Beginner,
            isPublished: false,
            items: [new WorkoutPlanItem(0, $curl->id(), 0, 3, 12)],
        );

        // Библиотека клиента видит только опубликованные и активные планы платформы
        $library = $this->plans->listLibraryForClient();
        $this->assertSame([$libraryPlan->id()], array_map(static fn (WorkoutPlan $p): int => $p->id(), $library));
        $this->assertSame([$squat->id(), $bench->id()], array_map(static fn (WorkoutPlanItem $i): int => $i->exerciseId(), $library[0]->items()));

        $this->assertNull($this->plans->findPublishedLibraryById($draftPlan->id()));
        $this->assertNull($this->plans->findPublishedLibraryById($personalPlan->id()));
        $this->assertNull($this->plans->findPlatformById($personalPlan->id()));
        $this->assertNull($this->plans->findOwnedById($user->id + 999, $personalPlan->id()));

        // Платформа видит оба платформенных плана
        $this->assertCount(2, $this->plans->listForPlatform());
        $this->assertCount(1, $this->plans->listForPlatform(onlyPublished: true));
        $this->assertCount(1, $this->plans->listForPlatform(level: PlanLevel::Advanced, query: 'draft'));

        // Клиент видит только свои активные планы
        $this->assertCount(1, $this->plans->listForClient($user->id));

        // Обновление состава плана заменяет упражнения в БД
        $libraryPlan->update(
            title: 'Updated Full Body',
            description: 'New description',
            level: PlanLevel::Intermediate,
            items: [new WorkoutPlanItem(0, $bench->id(), 0, 5, 5, 70000, 150)],
        );
        $updated = $this->plans->save($libraryPlan);

        $this->assertSame('Updated Full Body', $updated->title());
        $this->assertCount(1, $updated->items());
        $this->assertSame($bench->id(), $updated->items()[0]->exerciseId());
        $this->assertSame(70000, $updated->items()[0]->targetWeightGrams());
    }
}
