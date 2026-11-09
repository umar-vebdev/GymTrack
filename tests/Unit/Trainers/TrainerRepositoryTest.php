<?php

declare(strict_types=1);

namespace Tests\Unit\Trainers;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Modules\Trainers\Infrastructure\Persistence\TrainerModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrainerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, gymId: int}
     */
    private function createCompanyWithGym(string $name): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$name}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$name}", null);

        return ['companyId' => $companyId, 'gymId' => $gymId];
    }

    public function test_it_creates_active_trainer_with_public_ulid_and_normalized_fields(): void
    {
        $a = $this->createCompanyWithGym('A');
        $repo = $this->app->make(TrainerRepositoryInterface::class);

        $trainer = $repo->create($a['companyId'], $a['gymId'], '  Иван  ', '  ');

        $this->assertSame('Иван', $trainer->name());
        $this->assertNull($trainer->bio());
        $this->assertTrue($trainer->isActive());
        $this->assertNull($trainer->userId());
        $this->assertSame($a['companyId'], $trainer->companyId());
        $this->assertSame($a['gymId'], $trainer->gymId());
        $this->assertSame(26, strlen($trainer->publicId()));

        $other = $repo->create($a['companyId'], $a['gymId'], 'Олег');
        $this->assertNotSame($trainer->publicId(), $other->publicId());
    }

    public function test_it_rejects_invalid_profile_without_inserting_a_row(): void
    {
        $a = $this->createCompanyWithGym('A');
        $repo = $this->app->make(TrainerRepositoryInterface::class);

        try {
            $repo->create($a['companyId'], $a['gymId'], '   ');
            $this->fail('Blank name must be rejected.');
        } catch (InvalidTrainerProfile) {
            $this->assertSame(0, TrainerModel::query()->withoutGlobalScopes()->count());
        }
    }

    public function test_it_saves_changes_and_isolates_companies(): void
    {
        $a = $this->createCompanyWithGym('A');
        $b = $this->createCompanyWithGym('B');
        $repo = $this->app->make(TrainerRepositoryInterface::class);

        $zed = $repo->create($a['companyId'], $a['gymId'], 'Зубов');
        $alpha = $repo->create($a['companyId'], $a['gymId'], 'Антонов');
        $foreign = $repo->create($b['companyId'], $b['gymId'], 'Чужой');

        $zed->updateProfile('Зубов Пётр', 'Кроссфит');
        $zed->deactivate();
        $repo->save($zed);

        $reloaded = $repo->findById($a['companyId'], $zed->id()) ?? $this->fail('Trainer not found');
        $this->assertSame('Зубов Пётр', $reloaded->name());
        $this->assertSame('Кроссфит', $reloaded->bio());
        $this->assertFalse($reloaded->isActive());

        $names = array_map(static fn ($t): string => $t->name(), $repo->listForGym($a['companyId'], $a['gymId']));
        $this->assertSame(['Антонов', 'Зубов Пётр'], $names);

        $this->assertNull($repo->findById($a['companyId'], $foreign->id()));
        $this->assertNull($repo->findById($b['companyId'], $alpha->id()));
        $this->assertSame([], $repo->listForGym($b['companyId'], $a['gymId']));

        // save() фильтрует по компании сущности: подмена ID чужого тренера ничего не меняет
        $forged = new Trainer($foreign->id(), $foreign->publicId(), $a['companyId'], $a['gymId'], null, 'Взломан', null, false);
        $repo->save($forged);

        $untouched = $repo->findById($b['companyId'], $foreign->id()) ?? $this->fail('Trainer not found');
        $this->assertSame('Чужой', $untouched->name());
        $this->assertTrue($untouched->isActive());
    }

    public function test_one_account_can_be_trainer_only_once_per_company(): void
    {
        $a = $this->createCompanyWithGym('A');
        $user = User::factory()->create();
        $repo = $this->app->make(TrainerRepositoryInterface::class);

        $first = $repo->create($a['companyId'], $a['gymId'], 'Первый', null, $user->id);
        $this->assertSame($user->id, $first->userId());

        // без аккаунта тренеров может быть сколько угодно
        $repo->create($a['companyId'], $a['gymId'], 'Без входа 1');
        $repo->create($a['companyId'], $a['gymId'], 'Без входа 2');

        $this->expectException(QueryException::class);
        $repo->create($a['companyId'], $a['gymId'], 'Дубль', null, $user->id);
    }
}
