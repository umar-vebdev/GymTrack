<?php

declare(strict_types=1);

namespace Tests\Unit\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\Exceptions\InvalidExercise;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Infrastructure\Persistence\ExerciseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExerciseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_saves_and_isolates_platform_and_client_exercises(): void
    {
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();
        $repo = $this->app->make(ExerciseRepositoryInterface::class);

        $bench = $repo->create(null, '  Жим штанги лёжа  ', MuscleGroup::CHEST, '  Базовое упражнение  ');
        $squat = $repo->create(null, 'Приседания', MuscleGroup::LEGS);
        $archived = $repo->create(null, 'Архивная тяга', MuscleGroup::BACK);
        $archived->deactivate();
        $repo->save($archived);

        $personalA = $repo->create($clientA->id, 'Болгарские выпады 100%', MuscleGroup::LEGS);
        $personalB = $repo->create($clientB->id, 'Личное клиента B', MuscleGroup::ARMS);

        $this->assertSame(26, strlen($bench->publicId()));
        $this->assertSame('Жим штанги лёжа', $bench->name());
        $this->assertSame('Базовое упражнение', $bench->description());
        $this->assertTrue($bench->isPlatform());

        // Платформа видит все платформенные (включая неактивные), но не личные упражнения клиентов
        $allPlatform = $repo->listForPlatform();
        $this->assertSame(['Архивная тяга', 'Жим штанги лёжа', 'Приседания'], array_map(
            static fn (Exercise $e): string => $e->name(),
            $allPlatform,
        ));
        $this->assertCount(2, $repo->listForPlatform(onlyActive: true));
        $this->assertCount(1, $repo->listForPlatform(onlyActive: false));
        $this->assertNull($repo->findPlatformById($personalA->id()));

        // Клиент A видит активные платформенные + только свои личные (и не видит архивные / клиента B)
        $clientAList = $repo->listForClient($clientA->id);
        $this->assertSame(['Болгарские выпады 100%', 'Жим штанги лёжа', 'Приседания'], array_map(
            static fn (Exercise $e): string => $e->name(),
            $clientAList,
        ));

        // Фильтрация по группе мышц и подстроке (с экранированием % и регистронезависимо)
        $filteredLegs = $repo->listForClient($clientA->id, MuscleGroup::LEGS, '100%');
        $this->assertCount(1, $filteredLegs);
        $this->assertSame($personalA->id(), $filteredLegs[0]->id());

        $this->assertNotNull($repo->findAvailableForClient($clientA->id, $bench->id()));
        $this->assertNotNull($repo->findAvailableForClient($clientA->id, $personalA->id()));
        $this->assertNull($repo->findAvailableForClient($clientA->id, $archived->id()));
        $this->assertNull($repo->findAvailableForClient($clientA->id, $personalB->id()));

        // Попытка сохранить под чужим владельцем ничего не меняет
        $forged = new Exercise(
            id: $personalB->id(),
            publicId: $personalB->publicId(),
            userId: $clientA->id,
            name: 'Перезаписано',
            muscleGroup: MuscleGroup::CHEST,
            description: null,
            isActive: false,
        );
        $repo->save($forged);

        $untouched = $repo->findOwnedById($clientB->id, $personalB->id()) ?? $this->fail('Exercise not found');
        $this->assertSame('Личное клиента B', $untouched->name());
        $this->assertTrue($untouched->isActive());
    }

    public function test_it_rejects_invalid_data_without_inserting_row(): void
    {
        $repo = $this->app->make(ExerciseRepositoryInterface::class);

        try {
            $repo->create(null, '   ', MuscleGroup::CORE);
            $this->fail('Blank exercise name must be rejected.');
        } catch (InvalidExercise) {
            $this->assertSame(0, ExerciseModel::query()->count());
        }
    }
}
