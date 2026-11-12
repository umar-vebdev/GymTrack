<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Modules\Workouts\Domain\Exceptions\InvalidExercise;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\MuscleGroup;
use PHPUnit\Framework\TestCase;

final class ExerciseTest extends TestCase
{
    public function test_it_normalizes_fields_and_tracks_ownership_and_status(): void
    {
        $platform = new Exercise(
            id: 1,
            publicId: '01J00000000000000000000001',
            userId: null,
            name: '  Жим лёжа  ',
            muscleGroup: MuscleGroup::CHEST,
            description: '   ',
            isActive: true,
        );

        $this->assertTrue($platform->isPlatform());
        $this->assertFalse($platform->isOwnedBy(10));
        $this->assertSame('Жим лёжа', $platform->name());
        $this->assertNull($platform->description());
        $this->assertTrue($platform->isActive());

        $platform->update('  Жим на наклонной  ', MuscleGroup::SHOULDERS, '  Верх груди и передние дельты  ');
        $platform->deactivate();
        $this->assertSame('Жим на наклонной', $platform->name());
        $this->assertSame(MuscleGroup::SHOULDERS, $platform->muscleGroup());
        $this->assertSame('Верх груди и передние дельты', $platform->description());
        $this->assertFalse($platform->isActive());

        $platform->activate();
        $this->assertTrue($platform->isActive());

        $personal = new Exercise(
            id: 2,
            publicId: '01J00000000000000000000002',
            userId: 42,
            name: 'Болгарские выпады',
            muscleGroup: MuscleGroup::LEGS,
            description: null,
            isActive: true,
        );

        $this->assertFalse($personal->isPlatform());
        $this->assertTrue($personal->isOwnedBy(42));
        $this->assertFalse($personal->isOwnedBy(99));
    }

    public function test_it_rejects_invalid_name_description_and_owner(): void
    {
        $invalid = [
            static fn (): Exercise => new Exercise(1, '01J', null, '   ', MuscleGroup::CHEST, null, true),
            static fn (): Exercise => new Exercise(1, '01J', null, str_repeat('а', 101), MuscleGroup::CHEST, null, true),
            static fn (): Exercise => new Exercise(1, '01J', null, 'Жим', MuscleGroup::CHEST, str_repeat('б', 1001), true),
            static fn (): Exercise => new Exercise(1, '01J', 0, 'Жим', MuscleGroup::CHEST, null, true),
            static fn (): Exercise => new Exercise(1, '01J', -5, 'Жим', MuscleGroup::CHEST, null, true),
        ];

        foreach ($invalid as $index => $factory) {
            try {
                $factory();
                $this->fail("Case {$index} must throw InvalidExercise.");
            } catch (InvalidExercise) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

