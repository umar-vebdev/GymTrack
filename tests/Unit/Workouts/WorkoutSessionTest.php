<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use App\Modules\Workouts\Domain\WorkoutSession;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use App\Modules\Workouts\Domain\WorkoutSet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkoutSessionTest extends TestCase
{
    public function test_it_logs_sets_sequentially_and_completes_session(): void
    {
        $startedAt = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
        $session = new WorkoutSession(
            id: 1,
            publicId: '01S00000000000000000000001',
            userId: 42,
            planId: 7,
            title: '  Leg Day  ',
            status: WorkoutSessionStatus::InProgress,
            startedAt: $startedAt,
        );

        $this->assertTrue($session->isInProgress());
        $this->assertTrue($session->isOwnedBy(42));
        $this->assertSame('Leg Day', $session->title());

        $s1 = $session->logSet(10, 80000, 8, new DateTimeImmutable('2026-11-12T10:05:00+00:00'));
        $s2 = $session->logSet(11, 40000, 12, new DateTimeImmutable('2026-11-12T10:10:00+00:00'));
        $s3 = $session->logSet(10, 85000, 6, new DateTimeImmutable('2026-11-12T10:15:00+00:00'));

        $this->assertSame(1, $s1->setNumber());
        $this->assertSame(1, $s2->setNumber());
        $this->assertSame(2, $s3->setNumber());
        $this->assertSame(510000, $s3->volumeGrams());
        $this->assertCount(3, $session->sets());

        $finishedAt = new DateTimeImmutable('2026-11-12T10:50:00+00:00');
        $session->complete($finishedAt, '  Strong squats today  ');

        $this->assertFalse($session->isInProgress());
        $this->assertSame(WorkoutSessionStatus::Completed, $session->status());
        $this->assertSame($finishedAt, $session->finishedAt());
        $this->assertSame('Strong squats today', $session->notes());

        $this->expectException(InvalidWorkoutSession::class);
        $session->logSet(10, 85000, 6, new DateTimeImmutable('2026-11-12T10:55:00+00:00'));
    }

    public function test_it_rejects_finishing_before_start_and_invalid_set_values(): void
    {
        $startedAt = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
        $session = new WorkoutSession(
            id: 2,
            publicId: '01S00000000000000000000002',
            userId: 42,
            planId: null,
            title: 'Free Session',
            status: WorkoutSessionStatus::InProgress,
            startedAt: $startedAt,
        );

        try {
            $session->complete(new DateTimeImmutable('2026-11-12T09:59:00+00:00'));
            $this->fail('Expected InvalidWorkoutSession when finishedAt < startedAt.');
        } catch (InvalidWorkoutSession) {
            $this->addToAssertionCount(1);
        }

        try {
            new WorkoutSet(0, exerciseId: 1, setNumber: 1, weightGrams: -100, reps: 10, performedAt: $startedAt);
            $this->fail('Expected InvalidWorkoutSession for negative weight.');
        } catch (InvalidWorkoutSession) {
            $this->addToAssertionCount(1);
        }

        try {
            new WorkoutSet(0, exerciseId: 1, setNumber: 1, weightGrams: 0, reps: 0, performedAt: $startedAt);
            $this->fail('Expected InvalidWorkoutSession for zero reps.');
        } catch (InvalidWorkoutSession) {
            $this->addToAssertionCount(1);
        }

        $session->cancel(new DateTimeImmutable('2026-11-12T10:05:00+00:00'));
        $this->assertSame(WorkoutSessionStatus::Cancelled, $session->status());

        $this->expectException(InvalidWorkoutSession::class);
        $session->complete(new DateTimeImmutable('2026-11-12T10:06:00+00:00'));
    }
}
