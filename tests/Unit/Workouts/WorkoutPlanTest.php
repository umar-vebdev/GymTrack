<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutPlan;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use PHPUnit\Framework\TestCase;

final class WorkoutPlanTest extends TestCase
{
    public function test_it_creates_platform_plan_with_sorted_items_and_trimmed_fields(): void
    {
        $second = new WorkoutPlanItem(0, exerciseId: 2, sortOrder: 1, targetSets: 3, targetReps: 12, targetWeightGrams: 40000, restSeconds: 90);
        $first = new WorkoutPlanItem(0, exerciseId: 1, sortOrder: 0, targetSets: 4, targetReps: 8);

        $plan = new WorkoutPlan(
            id: 10,
            publicId: '01J00000000000000000000010',
            userId: null,
            sourcePlanId: null,
            title: '  Full Body Start  ',
            description: '   ',
            level: PlanLevel::Beginner,
            isPublished: true,
            isActive: true,
            items: [$second, $first],
        );

        $this->assertTrue($plan->isPlatform());
        $this->assertTrue($plan->isVisibleInLibrary());
        $this->assertSame('Full Body Start', $plan->title());
        $this->assertNull($plan->description());
        $this->assertSame([1, 2], array_map(static fn (WorkoutPlanItem $i): int => $i->exerciseId(), $plan->items()));
    }

    public function test_it_updates_plan_details_items_and_visibility(): void
    {
        $plan = new WorkoutPlan(
            id: 1,
            publicId: '01J00000000000000000000001',
            userId: null,
            sourcePlanId: null,
            title: 'Base Plan',
            description: null,
            level: PlanLevel::Beginner,
            isPublished: false,
            isActive: true,
        );

        $this->assertFalse($plan->isVisibleInLibrary());

        $plan->publish();
        $plan->update(
            title: 'Push Day',
            description: '  Upper body focus  ',
            level: PlanLevel::Intermediate,
            items: [new WorkoutPlanItem(0, exerciseId: 5, sortOrder: 0, targetSets: 4, targetReps: 10)],
        );

        $this->assertTrue($plan->isVisibleInLibrary());
        $this->assertSame('Push Day', $plan->title());
        $this->assertSame('Upper body focus', $plan->description());
        $this->assertSame(PlanLevel::Intermediate, $plan->level());
        $this->assertCount(1, $plan->items());

        $plan->deactivate();
        $this->assertFalse($plan->isVisibleInLibrary());
        $plan->activate();
        $plan->unpublish();
        $this->assertFalse($plan->isVisibleInLibrary());
    }

    public function test_it_rejects_invalid_plan_and_client_publish_invariants(): void
    {
        $clientPlan = new WorkoutPlan(
            id: 2,
            publicId: '01J00000000000000000000002',
            userId: 77,
            sourcePlanId: 10,
            title: 'My Split',
            description: 'Custom',
            level: PlanLevel::Advanced,
            isPublished: false,
            isActive: true,
        );

        $this->assertFalse($clientPlan->isPlatform());
        $this->assertTrue($clientPlan->isOwnedBy(77));
        $this->assertFalse($clientPlan->isOwnedBy(88));
        $this->assertSame(10, $clientPlan->sourcePlanId());

        $this->expectException(InvalidWorkoutPlan::class);
        $clientPlan->publish();
    }

    public function test_it_rejects_empty_or_too_long_title_and_invalid_item_ranges(): void
    {
        try {
            new WorkoutPlan(1, '01J00000000000000000000001', null, null, '   ', null, PlanLevel::Beginner, false, true);
            $this->fail('Expected InvalidWorkoutPlan for empty title.');
        } catch (InvalidWorkoutPlan) {
            $this->addToAssertionCount(1);
        }

        try {
            new WorkoutPlan(1, '01J00000000000000000000001', 0, null, 'Ok', null, PlanLevel::Beginner, false, true);
            $this->fail('Expected InvalidWorkoutPlan for zero userId.');
        } catch (InvalidWorkoutPlan) {
            $this->addToAssertionCount(1);
        }

        try {
            new WorkoutPlan(1, '01J00000000000000000000001', null, 5, 'Ok', null, PlanLevel::Beginner, false, true);
            $this->fail('Expected InvalidWorkoutPlan for platform plan with sourcePlanId.');
        } catch (InvalidWorkoutPlan) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidWorkoutPlan::class);
        new WorkoutPlanItem(0, exerciseId: 1, sortOrder: 0, targetSets: 0, targetReps: 10);
    }
}
